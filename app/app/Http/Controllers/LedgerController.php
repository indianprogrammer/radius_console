<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ledger — Billing & Invoices §5.5.
 *
 * A read-only, chronological account statement built by merging invoices
 * (debits) with payments (credits). Rows carry a running balance so the
 * closing figure equals total billed minus total collected.
 *
 * Without a `subscriber_id` the view shows the tenant-wide book; with one it
 * becomes that subscriber's statement.
 */
final class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));

        $subscriberId = $request->query('subscriber_id') ?: null;
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;

        $entries = $this->entries($subscriberId, $from, $to);

        // Running balance is computed oldest-first, then displayed newest-first.
        $balance = 0.0;
        $entries = $entries->map(function (array $row) use (&$balance) {
            $balance = round($balance + $row['debit'] - $row['credit'], 2);
            $row['balance'] = $balance;
            return $row;
        });

        $summary = [
            'debit'   => round($entries->sum('debit'), 2),
            'credit'  => round($entries->sum('credit'), 2),
            'closing' => $balance,
        ];

        $rows = $entries->reverse()->values();
        $paginator = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('ledger.index', [
            'entries'      => $paginator,
            'summary'      => $summary,
            'subscribers'  => Subscriber::where('tenant_id', tenant_id())
                ->orderBy('username')
                ->get(['id', 'username', 'first_name', 'last_name']),
            'subscriberId' => $subscriberId,
            'from'         => $from,
            'to'           => $to,
        ]);
    }

    /**
     * Merge invoices + payments into a single oldest-first collection.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function entries(?string $subscriberId, ?string $from, ?string $to)
    {
        $invoices = Invoice::where('tenant_id', tenant_id())
            ->where('status', '!=', 'void')
            ->when($subscriberId, fn ($q) => $q->where('subscriber_id', $subscriberId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->with('subscriber')
            ->get()
            ->map(fn (Invoice $i) => [
                'at'         => $i->created_at,
                'type'       => 'invoice',
                // The view renders a type pill, so the label carries only the
                // document number (and any qualifier) to avoid "Invoice Invoice".
                'label'      => $i->number,
                'reference'  => $i->due_date ? 'Due ' . $i->due_date->format('d/m/y') : null,
                'subscriber' => $i->subscriber?->username ?? '—',
                'debit'      => $i->payableAmount(),
                'credit'     => 0.0,
                'url'        => route('invoices.show', $i->id),
            ]);

        $payments = Payment::where('tenant_id', tenant_id())
            ->where('status', 'completed')
            ->when($subscriberId, fn ($q) => $q->where('subscriber_id', $subscriberId))
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to . ' 23:59:59'))
            ->with(['subscriber', 'invoice'])
            ->get()
            ->map(fn (Payment $p) => [
                'at'         => $p->paid_at,
                'type'       => 'payment',
                'label'      => $p->number . ' (' . $p->methodLabel() . ')',
                'reference'  => $p->reference ?: $p->invoice?->number,
                'subscriber' => $p->subscriber?->username ?? '—',
                'debit'      => 0.0,
                'credit'     => (float) $p->amount,
                'url'        => route('payments.edit', $p->id),
            ]);

        return $invoices->concat($payments)
            ->sortBy(fn (array $row) => optional($row['at'])->timestamp ?? 0)
            ->values();
    }
}
