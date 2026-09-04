<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side proxy for address search (forward geocoding) and pin lookup
 * (reverse geocoding), used by the subscriber Installation Address map.
 *
 * PROVIDER: Google Maps Platform (Geocoding API) when `services.google_maps.key`
 * is configured, otherwise OpenStreetMap Nominatim. The fallback exists so a
 * dev machine without a billing-enabled Google project still has a working
 * form; both paths emit the SAME response shape, so the browser never has to
 * care which one answered.
 *
 * Why proxy at all instead of calling the provider from the browser:
 *  - The Google key stays server-side. A key embedded in the page can be
 *    lifted and billed against; this one can be IP-restricted instead.
 *  - Nominatim REQUIRES an identifying User-Agent, which a browser fetch
 *    cannot set.
 *  - Responses are cached, which matters for quota (Google bills per request)
 *    and rate limits (Nominatim allows ~1 request/second).
 *
 * Only the handful of fields the form consumes is returned; the raw upstream
 * payload is never forwarded to the browser.
 */
class GeocodeController extends Controller
{
    private const GOOGLE_BASE = 'https://maps.googleapis.com/maps/api/geocode/json';

    private const NOMINATIM_BASE = 'https://nominatim.openstreetmap.org';

    /** Nominatim asks for a contactable identity on every request. */
    private const USER_AGENT = 'RadiusConsole/2.0 (ISP subscriber management)';

    /** Cache TTL — addresses are effectively static. */
    private const TTL = 86400;

    /**
     * Forward geocode: free-text address -> candidate places with coordinates.
     * GET /geocode/search?q=...
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:3|max:200',
        ]);

        $query = trim($data['q']);
        $cacheKey = 'geocode:search:' . $this->provider() . ':' . sha1(mb_strtolower($query));

        // Deliberately Cache::get + Cache::put rather than remember(): a failed
        // lookup must NOT be cached, or one upstream blip poisons the key for
        // the whole TTL.
        $results = Cache::get($cacheKey);

        if ($results === null) {
            $results = $this->usingGoogle()
                ? $this->googleSearch($query)
                : $this->nominatimSearch($query);

            if ($results !== null) {
                Cache::put($cacheKey, $results, self::TTL);
            }
        }

        if ($results === null) {
            return response()->json([
                'error' => 'Address search is unavailable right now. Type the address manually or drag the map pin.',
            ], 503);
        }

        return response()->json($results);
    }

    /**
     * Reverse geocode: map pin -> a structured address.
     * GET /geocode/reverse?lat=..&lon=..
     */
    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
        ]);

        // 5 decimals ≈ 1 m, finer than any building. Rounding the cache key
        // means nudging the pin a few centimetres reuses the hit.
        $lat = round((float) $data['lat'], 5);
        $lon = round((float) $data['lon'], 5);

        $cacheKey = "geocode:reverse:{$this->provider()}:{$lat},{$lon}";

        $result = Cache::get($cacheKey);

        if ($result === null) {
            $result = $this->usingGoogle()
                ? $this->googleReverse($lat, $lon)
                : $this->nominatimReverse($lat, $lon);

            if ($result !== null) {
                Cache::put($cacheKey, $result, self::TTL);
            }
        }

        if ($result === null) {
            return response()->json([
                'error' => 'Could not resolve that location. The coordinates were still saved.',
            ], 503);
        }

        return response()->json($result);
    }

    // ── Provider selection ───────────────────────────────────────────────

    private function googleKey(): ?string
    {
        $key = config('services.google_maps.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function usingGoogle(): bool
    {
        return $this->googleKey() !== null;
    }

    /** Namespaces the cache so switching providers cannot serve a stale shape. */
    private function provider(): string
    {
        return $this->usingGoogle() ? 'google' : 'osm';
    }

    // ── Google Maps Platform ─────────────────────────────────────────────

    private function googleSearch(string $query): ?array
    {
        $payload = $this->callGoogle(['address' => $query]);

        if ($payload === null) {
            return null;
        }

        return array_map(
            fn (array $row) => $this->shapeGoogle($row),
            array_slice($payload, 0, 8)
        );
    }

    private function googleReverse(float $lat, float $lon): ?array
    {
        $payload = $this->callGoogle(['latlng' => $lat . ',' . $lon]);

        if ($payload === null || $payload === []) {
            return null;
        }

        // Google orders results most-specific first.
        return $this->shapeGoogle($payload[0]);
    }

    /**
     * Issue a Google Geocoding API request and return its `results` array.
     *
     * Google signals failure in the BODY, not the HTTP status: a quota or key
     * problem still returns 200 with `status: REQUEST_DENIED`. Mapping those to
     * null makes callers surface a 503 instead of an empty list, which would
     * otherwise be indistinguishable from "no such address".
     */
    private function callGoogle(array $params): ?array
    {
        try {
            $response = Http::timeout(8)
                ->retry(2, 300)
                ->get(self::GOOGLE_BASE, $params + [
                    'key'      => $this->googleKey(),
                    'region'   => config('services.google_maps.region'),
                    'language' => config('services.google_maps.language'),
                ]);

            if (! $response->successful()) {
                Log::warning('Google geocode HTTP error', ['status' => $response->status()]);

                return null;
            }

            $body = $response->json();
            $status = $body['status'] ?? 'UNKNOWN';

            if ($status === 'ZERO_RESULTS') {
                return []; // A valid "nothing matched" — not an error.
            }

            if ($status !== 'OK') {
                // error_message carries the actionable detail, e.g. "This API
                // project is not authorized to use this API".
                Log::warning('Google geocode returned a non-OK status', [
                    'status'  => $status,
                    'message' => $body['error_message'] ?? null,
                ]);

                return null;
            }

            return $body['results'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('Google geocode unreachable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Reduce a Google result to the shape the form consumes.
     *
     * Google returns locality data as a flat list of `address_components`, each
     * tagged with one or more `types`, so every field is resolved by scanning
     * for the first component carrying a matching type.
     */
    private function shapeGoogle(array $row): array
    {
        $components = $row['address_components'] ?? [];

        $component = function (array $types) use ($components): ?string {
            foreach ($types as $type) {
                foreach ($components as $c) {
                    if (in_array($type, $c['types'] ?? [], true) && ! empty($c['long_name'])) {
                        return (string) $c['long_name'];
                    }
                }
            }

            return null;
        };

        // Street line: number + road, falling back to the sub-locality when the
        // pin is not on an addressed building.
        $street = trim(implode(' ', array_filter([
            $component(['street_number']),
            $component(['route']),
        ])));

        if ($street === '') {
            $street = $component(['sublocality_level_1', 'sublocality', 'neighborhood', 'premise']) ?? '';
        }

        return [
            'label'   => $row['formatted_address'] ?? '',
            'street'  => $street,
            'city'    => $component(['locality', 'postal_town', 'administrative_area_level_3', 'administrative_area_level_2']),
            'state'   => $component(['administrative_area_level_1']),
            'zip'     => $component(['postal_code']),
            'country' => $component(['country']),
            'lat'     => isset($row['geometry']['location']['lat']) ? (float) $row['geometry']['location']['lat'] : null,
            'lon'     => isset($row['geometry']['location']['lng']) ? (float) $row['geometry']['location']['lng'] : null,
        ];
    }

    // ── OpenStreetMap Nominatim (used when no Google key is set) ─────────

    private function nominatimSearch(string $query): ?array
    {
        $payload = $this->callNominatim('/search', [
            'q'              => $query,
            'format'         => 'jsonv2',
            'addressdetails' => 1,
            'limit'          => 8,
        ]);

        if ($payload === null) {
            return null;
        }

        return array_map(fn (array $row) => $this->shapeNominatim($row), $payload);
    }

    private function nominatimReverse(float $lat, float $lon): ?array
    {
        $payload = $this->callNominatim('/reverse', [
            'lat'            => $lat,
            'lon'            => $lon,
            'format'         => 'jsonv2',
            'addressdetails' => 1,
            'zoom'           => 18,
        ]);

        if ($payload === null || $payload === []) {
            return null;
        }

        return $this->shapeNominatim($payload);
    }

    private function callNominatim(string $path, array $query): ?array
    {
        try {
            $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => 'application/json',
                ])
                ->timeout(8)
                ->retry(2, 300)
                ->get(self::NOMINATIM_BASE . $path, $query);

            if (! $response->successful()) {
                Log::warning('Nominatim returned an error', [
                    'path'   => $path,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $decoded = $response->json();

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Nominatim unreachable', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Reduce a Nominatim result to the same shape as shapeGoogle().
     *
     * Nominatim spreads the locality across several optional keys depending on
     * settlement type (city / town / village / municipality ...), so each field
     * coalesces over the plausible candidates.
     */
    private function shapeNominatim(array $row): array
    {
        $a = $row['address'] ?? [];

        $pick = function (array $keys) use ($a): ?string {
            foreach ($keys as $key) {
                if (! empty($a[$key])) {
                    return (string) $a[$key];
                }
            }

            return null;
        };

        $street = trim(implode(' ', array_filter([
            $a['house_number'] ?? null,
            $a['road'] ?? null,
        ])));

        if ($street === '') {
            $street = $pick(['neighbourhood', 'suburb', 'hamlet']) ?? '';
        }

        return [
            'label'   => $row['display_name'] ?? '',
            'street'  => $street,
            'city'    => $pick(['city', 'town', 'village', 'municipality', 'county']),
            'state'   => $pick(['state', 'region']),
            'zip'     => $pick(['postcode']),
            'country' => $pick(['country']),
            'lat'     => isset($row['lat']) ? (float) $row['lat'] : null,
            'lon'     => isset($row['lon']) ? (float) $row['lon'] : null,
        ];
    }
}
