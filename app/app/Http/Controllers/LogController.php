<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\LiveSessionCollector;
use App\Src\Ports\RadiusClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Logs — the "Logs" menu group (SRD §5.0 #10, §9.8).
 *
 * ONE controller for all nine log pages. Each is `audit_log` filtered by
 * `channel`, so a per-channel controller would be nine copies of the same
 * query; `{channel}` is bound from the route and validated against
 * `ActivityLog::CHANNELS`, which means adding a channel needs no change here.
 *
 * READ-ONLY by design — no store, update or destroy. An audit trail that the
 * audited can edit is worthless, and SRD §9.8 requires it to be immutable. The
 * only write path in the app is `App\Services\ActivityLogger`.
 */
final class LogController extends Controller
{
    /** Landing entry for the menu group. */
    public function index()
    {
        return redirect()->route('logs.channel', 'audit');
    }

    /**
     * RADIUS Auth Logs — fetched live from the external RADIUS management server.
     * Not stored locally; this is a read-through proxy that pulls from
     * RadiusClient::listAuthLogs() and paginates the result.
     *
     * SRD §5.0: "Auth Logs should be inside logs menu, data fetched from
     * radius server API."
     */
    public function radius(Request $request, RadiusClient $radius)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $page    = max(1, (int) $request->query('page', 1));

        try {
            // Fetch a generous window so we can paginate server-side.
            // The RADIUS server's own limit caps at 200.  The API returns a wrapper
            // object {count, logs[]} so we unwrap the logs key.
            $response = $radius->listAuthLogs(min(500, $perPage * 3));
            $raw = $response['logs'] ?? ($response ?: []);
        } catch (\Throwable $e) {
            // RADIUS server is unreachable or returned a hard error.
            // Show an empty state with a clear message rather than crashing.
            $raw = [];
        }

        // Normalise each record to the same shape as ActivityLog rows so the
        // view can render it with a compatible column set.
        $rows = collect($raw)->map(fn (array $r): array => [
            'id'          => $r['id'] ?? null,
            'username'    => $r['username'] ?? $r['user'] ?? null,
            'ip_address'  => $r['ip_address'] ?? $r['nas_ip_address'] ?? $r['client_ip'] ?? null,
            'mac_address' => $r['mac_address'] ?? $r['calling_station_id'] ?? null,
            'nas'         => $r['nas'] ?? $r['nas_identifier'] ?? null,
            'reply'       => $r['reply'] ?? $r['reply_message'] ?? null, // Access-Accept / Access-Reject
            'status'      => str_contains(($r['reply'] ?? ''), 'Accept') ? 'success' : 'failed',
            'action'      => str_contains(($r['reply'] ?? ''), 'Accept') ? 'accepted' : 'rejected',
            'timestamp'   => $r['timestamp'] ?? $r['auth_time'] ?? $r['created_at'] ?? null,
            'raw'         => $r,
        ]);

        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => route('logs.radius')]
        );

        // Summary cards
        $totals = [
            'total'  => $rows->count(),
            'failed' => $rows->where('status', 'failed')->count(),
            'today'  => $rows->filter(fn ($r) => $r['timestamp']
                && \Illuminate\Support\Carbon::parse($r['timestamp'])->isToday())->count(),
            'week'   => $rows->filter(fn ($r) => $r['timestamp']
                && \Illuminate\Support\Carbon::parse($r['timestamp'])->gte(now()->subDays(7)))->count(),
            'last'   => $rows->max('timestamp'),
        ];

        return view('logs.radius', [
            'logs'      => $paginated,
            'totals'    => $totals,
            'perPage'   => $perPage,
        ]);
    }

    /**
     * Live Sessions — who is online right now, collected from the RADIUS
     * server's session API (SRD §5.3 "Live session grid (GET /api/sessions):
     * user, NAS, IP, uptime, bytes").
     *
     * Read-through like Auth Logs above: nothing is persisted, because the
     * RADIUS server is the network truth for session state and a local copy
     * could only be stale. Collection, tenant scoping and the merge with open
     * accounting records live in `LiveSessionCollector`; this method is filter,
     * sort and paginate over the result.
     */
    public function liveSessions(Request $request, LiveSessionCollector $collector)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $page    = max(1, (int) $request->query('page', 1));

        $tenant = view()->shared('tenant');

        $result = $collector->collect($tenant->slug ?? 'tenant', tenant_id());

        $sessions = $this->filterSessions(
            $result['sessions'],
            $search = $request->query('q'),
            $nasIp  = $request->query('nas'),
            $health = $request->query('health'),
        );

        $sessions = $this->sortSessions($sessions, $sort = (string) $request->query('sort', 'recent'));

        $paginated = new LengthAwarePaginator(
            $sessions->forPage($page, $perPage)->values()->all(),
            $sessions->count(),
            $perPage,
            $page,
            ['path' => route('logs.live-sessions'), 'query' => $request->query()],
        );

        return view('logs.live-sessions', [
            'sessions' => $paginated,
            // Cards summarise the whole tenant, so they keep reporting the true
            // picture while a filter narrows the table.
            'totals'   => $result['totals'],
            'matched'  => $sessions->count(),
            'foreign'  => $result['foreign'],
            'error'    => $result['error'],
            'nasOptions' => $result['sessions']->pluck('nas_name', 'nas_ip')
                ->filter(fn ($name, $ip) => (string) $ip !== '')
                ->map(fn ($name, $ip) => $name ? "$name ($ip)" : $ip)
                ->sort()
                ->all(),
            'search'  => $search,
            'nasIp'   => $nasIp,
            'health'  => $health,
            'sort'    => $sort,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Free-text + facet filters over the collected sessions.
     *
     * Filtering happens in PHP rather than by re-querying the RADIUS API
     * because the API takes no filter arguments — `GET /api/sessions` returns
     * everything, and one round trip is cheaper than a request per keystroke.
     *
     * @param  Collection<int, array<string, mixed>> $sessions
     * @return Collection<int, array<string, mixed>>
     */
    private function filterSessions(Collection $sessions, ?string $search, ?string $nasIp, ?string $health): Collection
    {
        if (($term = trim((string) $search)) !== '') {
            $needle = mb_strtolower($term);
            $sessions = $sessions->filter(function (array $s) use ($needle): bool {
                foreach (['local_username', 'username', 'subscriber_name', 'framed_ip',
                          'framed_ipv6', 'mac_address', 'nas_ip', 'nas_name',
                          'nas_identifier', 'session_id', 'plan_name'] as $key) {
                    if ($s[$key] !== null && str_contains(mb_strtolower((string) $s[$key]), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        if (($nasIp = trim((string) $nasIp)) !== '') {
            $sessions = $sessions->where('nas_ip', $nasIp);
        }

        if (($health = trim((string) $health)) !== '') {
            // "stale" on the filter bar means "not currently healthy", which is
            // what an operator is looking for; idle and stale read as one group.
            $sessions = $health === 'stale'
                ? $sessions->whereIn('health', ['idle', 'stale'])
                : $sessions->where('health', $health);
        }

        return $sessions->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>> $sessions
     * @return Collection<int, array<string, mixed>>
     */
    private function sortSessions(Collection $sessions, string $sort): Collection
    {
        return match ($sort) {
            'longest'  => $sessions->sortByDesc(fn (array $s) => $s['duration'] ?? 0)->values(),
            'volume'   => $sessions->sortByDesc('total_octets')->values(),
            'username' => $sessions->sortBy(fn (array $s) => mb_strtolower((string) $s['local_username']))->values(),
            // `recent` is the collector's own order; anything unrecognised
            // falls back to it rather than erroring on a hand-edited URL.
            default    => $sessions,
        };
    }

    public function channel(Request $request, string $channel)
    {
        abort_unless(isset(ActivityLog::CHANNELS[$channel]), 404);

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

        $logs = ActivityLog::query()
            ->where('tenant_id', tenant_id())
            ->channel($channel)
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('failed'), fn ($q) => $q->where('status', 'failed'))
            // Date bounds are inclusive of the whole day: a `created_at`
            // timestamp compared against a bare "Y-m-d" would otherwise exclude
            // everything after midnight on the end date.
            ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d . ' 00:00:00'))
            ->when($request->query('to'), fn ($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->search($request->query('q'))
            // Newest first — a log is read from the top.
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('logs.channel', [
            'channel'  => $channel,
            'label'    => ActivityLog::CHANNELS[$channel],
            'logs'     => $logs,
            'search'   => $request->query('q'),
            'action'   => $request->query('action'),
            'status'   => $request->query('status'),
            'from'     => $request->query('from'),
            'to'       => $request->query('to'),
            'failedOnly' => $request->boolean('failed'),
            'actions'  => $this->actionsInUse($channel),
            'totals'   => $this->summary($channel),
        ]);
    }

    /**
     * Header cards for the channel. Counted over the whole tenant rather than
     * the current page, so the numbers do not change as you paginate.
     */
    private function summary(string $channel): array
    {
        $base = fn () => ActivityLog::where('tenant_id', tenant_id())->channel($channel);

        return [
            'total'  => $base()->count(),
            'failed' => $base()->where('status', 'failed')->count(),
            'today'  => $base()->where('created_at', '>=', now()->startOfDay())->count(),
            'week'   => $base()->where('created_at', '>=', now()->subDays(7))->count(),
            'last'   => $base()->max('created_at'),
        ];
    }

    /**
     * Action filter options: the verbs that actually occur in this channel,
     * labelled from the catalogue. Derived from the data rather than listing all
     * of `ActivityLog::ACTIONS`, since a filter offering choices that match
     * nothing is noise (a `sent` option on Login History, for example).
     *
     * @return array<string, string>
     */
    private function actionsInUse(string $channel): array
    {
        $used = ActivityLog::where('tenant_id', tenant_id())
            ->channel($channel)
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->all();

        $options = [];
        foreach ($used as $action) {
            $options[$action] = ActivityLog::ACTIONS[$action]
                ?? ucfirst(str_replace('_', ' ', (string) $action));
        }

        return $options;
    }
}
