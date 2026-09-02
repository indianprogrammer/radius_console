<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

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
