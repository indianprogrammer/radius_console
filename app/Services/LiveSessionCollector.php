<?php

namespace App\Services;

use App\Models\Nas;
use App\Models\Subscriber;
use App\Src\Ports\RadiusClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Collects live session records from the EXTERNAL RADIUS server (SRD §5.3).
 *
 * Nothing here is stored. The RADIUS server is the network truth for who is
 * online (SRD §7.1: "PostgreSQL = business truth, RADIUS server = network
 * truth"), so a cached copy would only ever be a stale second opinion — the
 * page is a read-through proxy and every load asks the API.
 *
 * Two sources are merged, because the RADIUS core keeps two records of an open
 * session and they can legitimately disagree:
 *
 *  1. `GET /api/sessions`   → its `active_sessions` table, the authoritative
 *     "who is online right now" list, trimmed by a background job that deletes
 *     anything not updated for 30 minutes.
 *  2. `GET /api/accounting` → one row per session; a row with no `stop_time`
 *     is a session that never sent an Acct-Stop. These surface sessions the
 *     core lost from `active_sessions` (a restart, or the stale-session sweep
 *     firing while the NAS was still up) and would otherwise be invisible.
 *
 * Rows from (2) are marked so the operator can tell a confirmed-live session
 * from a recovered one instead of being handed a single unqualified list.
 *
 * TENANT ISOLATION (SRD §4.1.1) is this class's most important job. The RADIUS
 * core has no tenant concept and returns EVERY company's sessions, so a row is
 * kept only when its `username` is one of ours — either prefixed with this
 * tenant's slug (the namespace `Subscriber::radiusUsername()` writes) or
 * matching a stored `subscribers.radius_username`. Everything else is dropped
 * before it can reach a view.
 */
final class LiveSessionCollector
{
    /** No accounting update for this long ⇒ the session is shown as idle. */
    public const IDLE_AFTER_MINUTES = 5;

    /**
     * Matches the RADIUS core's own `cleanStaleSessions(30)` sweep: past this,
     * the session is about to be deleted server-side and should not be read as
     * a healthy connection.
     */
    public const STALE_AFTER_MINUTES = 30;

    /** Accounting window pulled for the open-session recovery pass. */
    private const ACCOUNTING_LIMIT = 500;

    public function __construct(private readonly RadiusClient $radius) {}

    /**
     * @return array{
     *     sessions: Collection<int, array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     fetched: int,
     *     foreign: int,
     *     error: string|null
     * }
     */
    public function collect(string $tenantSlug, string|int $tenantId): array
    {
        $error = null;
        $records = [];

        try {
            $records = $this->activeSessions();
        } catch (\Throwable $e) {
            // The server is down or rejected us. Report it, then still try
            // accounting — a partial answer beats a blank page.
            $error = $this->reason($e);
        }

        try {
            $records = $this->mergeOpenAccounting($records);
        } catch (\Throwable $e) {
            // Recovery is best-effort by definition; only surface its failure
            // if it is the sole reason we have nothing to show.
            $error ??= $records === [] ? $this->reason($e) : null;
        }

        $fetched = count($records);

        $mine = $this->scopeToTenant($records, $tenantSlug, $tenantId);
        $sessions = $this->enrich($mine, $tenantId)
            // Newest first: a session that just came up is the one being
            // watched. Sessions with no start time sort last.
            ->sortByDesc(fn (array $r) => $r['start_time']?->getTimestamp() ?? 0)
            ->values();

        return [
            'sessions' => $sessions,
            'totals'   => $this->totals($sessions),
            'fetched'  => $fetched,
            'foreign'  => $fetched - count($mine),
            'error'    => $error,
        ];
    }

    // ---- Sources ----------------------------------------------------------

    /**
     * `active_sessions` — the live list.
     *
     * @return array<string, array<string, mixed>> keyed by session id
     */
    private function activeSessions(): array
    {
        $response = $this->radius->listSessions();
        $rows = $response['sessions'] ?? (array_is_list($response) ? $response : []);

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $session = $this->normalise($row, 'live');
            $out[$session['session_id']] = $session;
        }

        return $out;
    }

    /**
     * Accounting rows with no `stop_time` that the live list does not already
     * contain. Keyed by session id, so a session present in both keeps its
     * authoritative `live` record.
     *
     * @param  array<string, array<string, mixed>> $records
     * @return array<string, array<string, mixed>>
     */
    private function mergeOpenAccounting(array $records): array
    {
        $response = $this->radius->listAccounting(self::ACCOUNTING_LIMIT);
        $rows = $response['records'] ?? (array_is_list($response) ? $response : []);

        foreach ($rows as $row) {
            if (!is_array($row) || ($row['stop_time'] ?? null) !== null) {
                continue;
            }

            $session = $this->normalise($row, 'accounting');
            if ($session['session_id'] === '' || isset($records[$session['session_id']])) {
                continue;
            }

            $records[$session['session_id']] = $session;
        }

        return $records;
    }

    // ---- Shaping ----------------------------------------------------------

    /**
     * One row from either source, reduced to a single shape.
     *
     * The two endpoints name the same things differently (`last_update` vs
     * `update_time`), so the view is spared knowing which source it is looking
     * at beyond the `source` flag itself.
     *
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalise(array $row, string $source): array
    {
        $start = $this->time($row['start_time'] ?? null);
        $seen  = $this->time($row['last_update'] ?? $row['update_time'] ?? null);

        $input  = (int) ($row['input_octets'] ?? 0);
        $output = (int) ($row['output_octets'] ?? 0);

        // Prefer the NAS's own Acct-Session-Time when it reported one; fall
        // back to wall-clock since start. Carbon 3 returns a float from
        // diffInSeconds, so cast — durationLabel() takes an int.
        $duration = isset($row['session_time'])
            ? (int) $row['session_time']
            : ($start ? (int) max(0, $start->diffInSeconds(now())) : null);

        return [
            'session_id'     => (string) ($row['session_id'] ?? ''),
            'username'       => (string) ($row['username'] ?? ''),
            'nas_ip'         => $row['nas_ip'] ?? null,
            'nas_identifier' => $row['nas_identifier'] ?? null,
            'framed_ip'      => $row['framed_ip'] ?? null,
            'framed_ipv6'    => $row['framed_ipv6'] ?? null,
            'mac_address'    => $row['mac_address'] ?? null,
            'start_time'     => $start,
            'last_seen'      => $seen,
            'input_octets'   => $input,
            'output_octets'  => $output,
            'total_octets'   => $input + $output,
            'duration'       => $duration,
            'source'         => $source,
            'raw'            => $row,
        ];
    }

    /**
     * Drop every record that does not belong to this tenant.
     *
     * @param  array<string, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function scopeToTenant(array $records, string $tenantSlug, string|int $tenantId): array
    {
        $prefix = $tenantSlug . '_';
        $names = array_values(array_unique(array_column($records, 'username')));

        // A stored mapping wins over the prefix rule: a subscriber provisioned
        // under a previous slug is still ours.
        $known = $names === [] ? [] : Subscriber::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('radius_username', $names)
            ->pluck('radius_username')
            ->all();
        $known = array_flip($known);

        return array_values(array_filter(
            $records,
            fn (array $r): bool => isset($known[$r['username']])
                || ($r['username'] !== '' && str_starts_with($r['username'], $prefix))
        ));
    }

    /**
     * Attach the local view of each session: which subscriber it is, what plan
     * they are on, and the friendly name of the NAS they came in through.
     *
     * @param  array<int, array<string, mixed>> $records
     * @return Collection<int, array<string, mixed>>
     */
    private function enrich(array $records, string|int $tenantId): Collection
    {
        $names = array_values(array_unique(array_column($records, 'username')));

        $subscribers = $names === [] ? collect() : Subscriber::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->whereIn('radius_username', $names)
            ->get()
            ->keyBy('radius_username');

        $nasNames = Nas::query()
            ->where('tenant_id', $tenantId)
            ->pluck('name', 'nas_ip');

        return collect($records)->map(function (array $r) use ($subscribers, $nasNames): array {
            $subscriber = $subscribers->get($r['username']);

            $r['subscriber_id'] = $subscriber?->id;
            $r['local_username'] = $subscriber->username
                // Unprovisioned or externally created: show the name without
                // the tenant namespace rather than a raw prefixed string.
                ?? (str_contains($r['username'], '_')
                    ? substr($r['username'], strpos($r['username'], '_') + 1)
                    : $r['username']);
            $r['subscriber_name'] = $subscriber
                ? trim(($subscriber->first_name ?? '') . ' ' . ($subscriber->last_name ?? '')) ?: null
                : null;
            $r['plan_name'] = $subscriber?->plan?->name;
            $r['nas_name'] = $r['nas_ip'] ? ($nasNames[$r['nas_ip']] ?? null) : null;
            $r['health'] = $this->health($r['last_seen']);
            $r['duration_label'] = self::durationLabel($r['duration']);

            return $r;
        });
    }

    /**
     * Freshness of the accounting stream for this session, which is the only
     * evidence available that the link is still up.
     */
    private function health(?Carbon $lastSeen): string
    {
        if ($lastSeen === null) {
            return 'unknown';
        }
        $minutes = $lastSeen->diffInMinutes(now());

        return match (true) {
            $minutes >= self::STALE_AFTER_MINUTES => 'stale',
            $minutes >= self::IDLE_AFTER_MINUTES  => 'idle',
            default                               => 'online',
        };
    }

    /**
     * Header cards. Counted over every session belonging to the tenant, not the
     * current page or the active search, so the numbers do not move while the
     * list is being filtered.
     *
     * @param  Collection<int, array<string, mixed>> $sessions
     * @return array<string, mixed>
     */
    private function totals(Collection $sessions): array
    {
        return [
            'total'       => $sessions->count(),
            'online'      => $sessions->where('health', 'online')->count(),
            'stale'       => $sessions->whereIn('health', ['idle', 'stale'])->count(),
            'recovered'   => $sessions->where('source', 'accounting')->count(),
            'subscribers' => $sessions->pluck('username')->unique()->count(),
            'nas'         => $sessions->pluck('nas_ip')->filter()->unique()->count(),
            'upload'      => (int) $sessions->sum('input_octets'),
            'download'    => (int) $sessions->sum('output_octets'),
            'volume'      => (int) $sessions->sum('total_octets'),
            'longest'     => self::durationLabel($sessions->max('duration')),
        ];
    }

    // ---- Formatting -------------------------------------------------------

    /**
     * Human-readable byte count, e.g. "1.5 GB".
     *
     * Hand-rolled rather than `Illuminate\Support\Number::fileSize()`, which
     * throws unless the `intl` extension is loaded — it is not on this
     * deployment, and a traffic column is not worth a hard extension dependency.
     */
    public static function bytesLabel(?int $bytes): string
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        // One decimal below 10 (2.4 GB reads better than 2 GB), none above.
        return ($value < 10 ? number_format($value, 1) : number_format($value)) . ' ' . $units[$unit];
    }

    /** Compact uptime, e.g. "3d 4h", "2h 15m", "48s". */
    public static function durationLabel(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        // Two units is enough to read an uptime at a glance; seconds only
        // matter for a session that just came up, handled above.
        return match (true) {
            $days > 0  => "{$days}d {$hours}h",
            $hours > 0 => "{$hours}h {$minutes}m",
            default    => "{$minutes}m",
        };
    }

    private function time(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            // A malformed timestamp must not take the whole page down.
            return null;
        }
    }

    /**
     * The adapter reports transport failures as "RADIUS GET /x failed: 500 …".
     * Keep it short enough for a banner and never leak the bearer token.
     */
    private function reason(\Throwable $e): string
    {
        return trim(str_replace(config('radius.password', ''), '***', $e->getMessage())) ?: 'Unknown error';
    }
}
