<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Address search / pin lookup behind the subscriber Installation Address map.
 *
 * The upstream provider is always faked here — these assert OUR contract
 * (provider selection, response shape, cache behaviour, failure handling),
 * never Google's or OSM's live output.
 */
class GeocodeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Geo ISP', 'domain' => 'geo.test', 'slug' => 'geo', 'status' => 'active',
        ]);

        Cache::flush();
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function useGoogle(): void
    {
        config(['services.google_maps.key' => 'test-key']);
    }

    private function useNominatim(): void
    {
        config(['services.google_maps.key' => null]);
    }

    /** A minimal but realistic Google Geocoding API result. */
    private function googleResult(): array
    {
        return [
            'status' => 'OK',
            'results' => [[
                'formatted_address' => '50, MG Road, Bengaluru, Karnataka 560001, India',
                'address_components' => [
                    ['long_name' => '50', 'types' => ['street_number']],
                    ['long_name' => 'Mahatma Gandhi Road', 'types' => ['route']],
                    ['long_name' => 'Bengaluru', 'types' => ['locality', 'political']],
                    ['long_name' => 'Karnataka', 'types' => ['administrative_area_level_1']],
                    ['long_name' => '560001', 'types' => ['postal_code']],
                    ['long_name' => 'India', 'types' => ['country']],
                ],
                'geometry' => ['location' => ['lat' => 12.9755, 'lng' => 77.6068]],
            ]],
        ];
    }

    public function test_search_uses_google_when_a_key_is_configured(): void
    {
        $this->useGoogle();

        Http::fake([
            'maps.googleapis.com/*' => Http::response($this->googleResult()),
            // Any Nominatim call would be a bug.
            'nominatim.openstreetmap.org/*' => Http::response([], 500),
        ]);

        $this->get($this->url('/geocode/search?q=MG Road Bengaluru'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.street', '50 Mahatma Gandhi Road')
            ->assertJsonPath('0.city', 'Bengaluru')
            ->assertJsonPath('0.state', 'Karnataka')
            ->assertJsonPath('0.zip', '560001')
            ->assertJsonPath('0.country', 'India')
            ->assertJsonPath('0.lat', 12.9755)
            ->assertJsonPath('0.lon', 77.6068);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'maps.googleapis.com')
            && str_contains($request->url(), 'key=test-key'));
    }

    public function test_search_falls_back_to_nominatim_without_a_google_key(): void
    {
        $this->useNominatim();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'display_name' => 'Mahatma Gandhi Road, Bengaluru, Karnataka, 560001, India',
                'lat' => '12.9755',
                'lon' => '77.6068',
                'address' => [
                    'road' => 'Mahatma Gandhi Road',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'postcode' => '560001',
                    'country' => 'India',
                ],
            ]]),
        ]);

        $this->get($this->url('/geocode/search?q=MG Road Bengaluru'))
            ->assertOk()
            ->assertJsonPath('0.city', 'Bengaluru')
            ->assertJsonPath('0.country', 'India');

        // Nominatim's policy requires an identifying User-Agent.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'nominatim.openstreetmap.org')
            && $request->hasHeader('User-Agent'));
    }

    public function test_reverse_geocode_shapes_a_google_pin_lookup(): void
    {
        $this->useGoogle();

        Http::fake(['maps.googleapis.com/*' => Http::response($this->googleResult())]);

        $this->get($this->url('/geocode/reverse?lat=12.9755&lon=77.6068'))
            ->assertOk()
            ->assertJsonPath('city', 'Bengaluru')
            ->assertJsonPath('label', '50, MG Road, Bengaluru, Karnataka 560001, India');
    }

    /**
     * Google reports quota / key failures as HTTP 200 with a non-OK body
     * status. Those must surface as an error, not as "no such address".
     */
    public function test_a_google_request_denied_becomes_a_503_not_an_empty_result(): void
    {
        $this->useGoogle();

        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => 'REQUEST_DENIED',
            'error_message' => 'The provided API key is invalid.',
        ])]);

        $this->get($this->url('/geocode/search?q=MG Road Bengaluru'))
            ->assertStatus(503)
            ->assertJsonStructure(['error']);
    }

    public function test_zero_results_is_an_empty_list_rather_than_an_error(): void
    {
        $this->useGoogle();

        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []])]);

        $this->get($this->url('/geocode/search?q=nowhere at all xyzzy'))
            ->assertOk()
            ->assertExactJson([]);
    }

    /** A failed lookup must not be cached, or one blip poisons the whole TTL. */
    public function test_a_failed_lookup_is_not_cached(): void
    {
        $this->useGoogle();

        Http::fakeSequence()
            ->push(['status' => 'OVER_QUERY_LIMIT'], 200)
            ->push($this->googleResult(), 200);

        $this->get($this->url('/geocode/search?q=MG Road Bengaluru'))->assertStatus(503);

        // Same query again — a cached failure would return 503 a second time.
        $this->get($this->url('/geocode/search?q=MG Road Bengaluru'))
            ->assertOk()
            ->assertJsonPath('0.city', 'Bengaluru');
    }

    public function test_a_successful_lookup_is_cached(): void
    {
        $this->useGoogle();

        Http::fake(['maps.googleapis.com/*' => Http::response($this->googleResult())]);

        $this->get($this->url('/geocode/search?q=MG Road Bengaluru'))->assertOk();
        $this->get($this->url('/geocode/search?q=mg road bengaluru'))->assertOk();

        // Case-insensitive cache key: the second request must not hit upstream.
        Http::assertSentCount(1);
    }

    public function test_search_rejects_a_too_short_query(): void
    {
        Http::fake();

        $this->get($this->url('/geocode/search?q=ab'))
            ->assertStatus(302); // Web route: validation failure redirects back.

        Http::assertNothingSent();
    }

    public function test_reverse_rejects_out_of_range_coordinates(): void
    {
        Http::fake();

        $this->get($this->url('/geocode/reverse?lat=99&lon=200'))
            ->assertStatus(302);

        Http::assertNothingSent();
    }
}
