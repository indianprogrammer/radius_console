<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Subscriber;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

/**
 * Invoices — Billing & Invoices §5.5.
 *
 * Invoices are generated from a subscriber's billing items (InvoiceService),
 * so this controller intentionally exposes generate / view / status changes
 * rather than free-form line-item editing.
 */
final class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $invoices = Invoice::query()
            ->where('tenant_id', tenant_id())
            ->with('subscriber')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhereHas('subscriber', fn ($s) => $s->where('username', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'search'   => $request->query('q'),
            'status'   => $request->query('status'),
            'totals'   => $this->summary(),
        ]);
    }

    public function create()
    {
        return view('invoices.create', [
            'subscribers' => $this->subscribers(),
        ]);
    }

    /**
     * Generate an invoice for a subscriber from their stored billing items.
     */
    public function store(Request $request, InvoiceService $invoices)
    {
        $data = $request->validate([
            'subscriber_id' => 'required|integer',
            'due_date'      => 'nullable|date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $subscriber = Subscriber::where('tenant_id', tenant_id())->findOrFail($data['subscriber_id']);
        $invoice = $invoices->generateFromSubscriber($subscriber);

        $invoice->fill(array_filter([
            'due_date' => $data['due_date'] ?? null,
            'notes'    => $data['notes'] ?? null,
        ]))->save();

        $invoice->refreshStatus();

        return redirect()->route('invoices.show', $invoice->id)
            ->with('status', "Invoice {$invoice->number} generated.");
    }

    public function show(int $id)
    {
        $invoice = Invoice::where('tenant_id', tenant_id())
            ->with(['items', 'payments' => fn ($q) => $q->orderByDesc('paid_at'), 'subscriber'])
            ->findOrFail($id);

        return view('invoices.show', ['invoice' => $invoice]);
    }

    public function edit(int $id)
    {
        $invoice = Invoice::where('tenant_id', tenant_id())->with('subscriber')->findOrFail($id);

        return view('invoices.edit', ['invoice' => $invoice]);
    }

    public function update(Request $request, int $id)
    {
        $invoice = Invoice::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'status'   => 'required|in:' . implode(',', array_keys(Invoice::STATUSES)),
            'due_date' => 'nullable|date',
            'notes'    => 'nullable|string|max:1000',
        ]);

        $invoice->update($data);

        // Re-derive from payments unless the user explicitly voided/drafted it.
        if (! in_array($data['status'], ['void', 'draft'], true)) {
            $invoice->refreshStatus();
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('status', "Invoice {$invoice->number} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $invoice = Invoice::where('tenant_id', tenant_id())->findOrFail($id);
        $number = $invoice->number;
        $invoice->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Invoice {$number} deleted."]);
        }

        return redirect()->route('invoices.index')->with('status', "Invoice {$number} deleted.");
    }

    /** Header figures: billed / collected / outstanding for the tenant. */
    private function summary(): array
    {
        $rows = Invoice::where('tenant_id', tenant_id())
            ->where('status', '!=', 'void')
            ->get(['total', 'amount', 'paid_amount', 'status', 'due_date']);

        $billed = $rows->sum(fn ($i) => $i->payableAmount());
        $paid   = $rows->sum(fn ($i) => (float) $i->paid_amount);

        return [
            'billed'      => round($billed, 2),
            'collected'   => round($paid, 2),
            'outstanding' => round(max(0, $billed - $paid), 2),
            'overdue'     => $rows->filter(fn ($i) => $i->isOverdue())->count(),
        ];
    }

    private function subscribers()
    {
        return Subscriber::where('tenant_id', tenant_id())
            ->orderBy('username')
            ->get(['id', 'username', 'first_name', 'last_name']);
    }
}
