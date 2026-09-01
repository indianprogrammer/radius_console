<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payments — Billing & Invoices §5.5.
 *
 * Every receipt is tenant + subscriber scoped. Saving / deleting a payment
 * recomputes the parent invoice's paid_amount and status so the ledger and
 * invoice list always agree.
 */
final class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $payments = Payment::query()
            ->where('tenant_id', tenant_id())
            ->with(['subscriber', 'invoice'])
            ->when($request->query('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%")
                      ->orWhereHas('subscriber', fn ($s) => $s->where('username', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $collected = Payment::where('tenant_id', tenant_id())
            ->where('status', 'completed')
            ->sum('amount');

        return view('payments.index', [
            'payments'  => $payments,
            'search'    => $request->query('q'),
            'method'    => $request->query('method'),
            'status'    => $request->query('status'),
            'collected' => round((float) $collected, 2),
        ]);
    }

    public function create(Request $request)
    {
        $invoiceId = $request->query('invoice_id');
        $invoice = $invoiceId
            ? Invoice::where('tenant_id', tenant_id())->find($invoiceId)
            : null;

        return view('payments.create', [
            'invoice'     => $invoice,
            'invoices'    => $this->openInvoices(),
            'subscribers' => $this->subscribers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $payment = DB::transaction(function () use ($data) {
            [$subscriberId, $invoice] = $this->resolveTargets($data);

            $payment = Payment::create([
                'tenant_id'     => tenant_id(),
                'subscriber_id' => $subscriberId,
                'invoice_id'    => $invoice?->id,
                'number'        => Payment::nextNumber(tenant_id()),
                'amount'        => (float) $data['amount'],
                'method'        => $data['method'],
                'reference'     => $data['reference'] ?? null,
                'paid_at'       => $data['paid_at'] ?? now(),
                'status'        => $data['status'] ?? 'completed',
                'notes'         => $data['notes'] ?? null,
            ]);

            $invoice?->refreshStatus();

            return $payment;
        });

        return redirect()->route('payments.index')
            ->with('status', "Payment {$payment->number} recorded.");
    }

    public function edit(int $id)
    {
        $payment = Payment::where('tenant_id', tenant_id())->findOrFail($id);

        return view('payments.edit', [
            'payment'     => $payment,
            'invoices'    => $this->openInvoices($payment->invoice_id),
            'subscribers' => $this->subscribers(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $payment = Payment::where('tenant_id', tenant_id())->findOrFail($id);
        $data = $this->validateData($request);

        DB::transaction(function () use ($payment, $data) {
            $previousInvoice = $payment->invoice;
            [$subscriberId, $invoice] = $this->resolveTargets($data);

            $payment->update([
                'subscriber_id' => $subscriberId,
                'invoice_id'    => $invoice?->id,
                'amount'        => (float) $data['amount'],
                'method'        => $data['method'],
                'reference'     => $data['reference'] ?? null,
                'paid_at'       => $data['paid_at'] ?? $payment->paid_at,
                'status'        => $data['status'] ?? 'completed',
                'notes'         => $data['notes'] ?? null,
            ]);

            // Re-derive both the old and the new invoice (the payment may have moved).
            $previousInvoice?->refreshStatus();
            if ($invoice && $invoice->id !== $previousInvoice?->id) {
                $invoice->refreshStatus();
            }
        });

        return redirect()->route('payments.index')
            ->with('status', "Payment {$payment->number} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $payment = Payment::where('tenant_id', tenant_id())->findOrFail($id);
        $number = $payment->number;

        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $payment->delete();
            $invoice?->refreshStatus();
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Payment {$number} deleted."]);
        }

        return redirect()->route('payments.index')->with('status', "Payment {$number} deleted.");
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'invoice_id'    => 'nullable|integer',
            'subscriber_id' => 'nullable|integer|required_without:invoice_id',
            'amount'        => 'required|numeric|min:0.01',
            'method'        => 'required|in:' . implode(',', array_keys(Payment::METHODS)),
            'reference'     => 'nullable|string|max:120',
            'paid_at'       => 'nullable|date',
            'status'        => 'nullable|in:' . implode(',', array_keys(Payment::STATUSES)),
            'notes'         => 'nullable|string|max:1000',
        ]);
    }

    /**
     * Resolve the tenant-scoped invoice (if any) and the subscriber the payment
     * belongs to. When an invoice is picked, its subscriber always wins so a
     * receipt can never be attached to the wrong account.
     *
     * @return array{0:int,1:?Invoice}
     */
    private function resolveTargets(array $data): array
    {
        $invoice = ! empty($data['invoice_id'])
            ? Invoice::where('tenant_id', tenant_id())->findOrFail($data['invoice_id'])
            : null;

        if ($invoice) {
            return [(int) $invoice->subscriber_id, $invoice];
        }

        $subscriber = Subscriber::where('tenant_id', tenant_id())->findOrFail($data['subscriber_id']);

        return [(int) $subscriber->id, null];
    }

    /** Invoices that still owe money (plus the one already linked, when editing). */
    private function openInvoices(?int $includeId = null)
    {
        return Invoice::where('tenant_id', tenant_id())
            ->with('subscriber')
            ->where(function ($q) use ($includeId) {
                $q->whereIn('status', ['draft', 'unpaid', 'partial']);
                if ($includeId) {
                    $q->orWhere('id', $includeId);
                }
            })
            ->orderByDesc('id')
            ->get();
    }

    private function subscribers()
    {
        return Subscriber::where('tenant_id', tenant_id())
            ->orderBy('username')
            ->get(['id', 'username', 'first_name', 'last_name']);
    }
}
