<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscriber;
use Carbon\Carbon;

/**
 * Generates invoices from subscriber billing items.
 *
 * When a subscriber is created / updated with billing items, this service
 * produces a single invoice covering all "one-time" and "refundable" items
 * plus schedules "recurring" items for future billing.
 *
 * Refundable items → stored on the invoice with is_refundable=true.
 * One-time items  → invoiced immediately.
 * Recurring items → scheduled (next_bill_at set); no invoice row created
 *                    on provision, but the invoice record may optionally
 *                    include the first recurring cycle.
 */
class InvoiceService
{
    /**
     * Generate / update the provisioning invoice for a subscriber.
     *
     * Creates one invoice (or re-uses an existing unpaid one) and attaches
     * line items for each billing item on the subscriber.
     *
     * @param  Subscriber  $subscriber
     * @param  array|null  $billingItems  Override items (falls back to $subscriber->billing_items)
     * @return Invoice
     */
    public function generateFromSubscriber(Subscriber $subscriber, ?array $billingItems = null): Invoice
    {
        $items = $billingItems ?? $subscriber->billing_items ?? [];
        if (empty($items)) {
            return $this->getOrCreateInvoice($subscriber);
        }

        $invoice = $this->getOrCreateInvoice($subscriber);
        $invoice = $this->syncItems($invoice, $subscriber, $items);

        // Recompute totals from items
        $this->recomputeTotals($invoice);

        return $invoice->fresh(['items']);
    }

    /**
     * Return an existing unpaid invoice for the subscriber, or create a new one.
     */
    protected function getOrCreateInvoice(Subscriber $subscriber): Invoice
    {
        $invoice = Invoice::where('subscriber_id', $subscriber->id)
            ->whereIn('status', ['unpaid', 'draft'])
            ->orderByDesc('id')
            ->first();

        if (!$invoice) {
            $invoice = Invoice::create([
                'tenant_id'     => $subscriber->tenant_id,
                'subscriber_id' => $subscriber->id,
                'number'        => $this->nextInvoiceNumber($subscriber->tenant_id),
                'status'        => 'unpaid',
                'due_date'      => Carbon::now()->addDays(15)->toDateString(),
            ]);
        }

        return $invoice;
    }

    /**
     * Sync line items on the invoice with the subscriber's billing items.
     */
    protected function syncItems(Invoice $invoice, Subscriber $subscriber, array $items): Invoice
    {
        // Remove stale items that are no longer on the subscriber
        $invoice->items()->delete();

        foreach ($items as $bi) {
            $type      = $bi['type']   ?? 'one-time';
            $label     = $bi['label']  ?? 'Item';
            $amount    = (float) ($bi['amount']  ?? 0);
            $qty       = max(1, (int)   ($bi['qty']     ?? 1));
            $taxable   = !empty($bi['taxable']);
            $cycle     = $bi['billing_cycle'] ?? null;
            $isRefund  = !empty($bi['is_refundable']);
            $productId = $bi['product_id'] ?? null;

            $unitPrice  = $amount;
            $lineAmount = round($unitPrice * $qty, 2);
            $taxRate    = $this->resolveTaxRate($subscriber);
            $taxAmount  = $taxable ? round($lineAmount * $taxRate / 100, 2) : 0;
            $lineTotal  = round($lineAmount + $taxAmount, 2);

            $nextBillAt = null;
            if ($type === 'recurring' && $cycle) {
                $nextBillAt = $this->computeNextBillDate($cycle);
            }

            InvoiceItem::create([
                'tenant_id'      => $subscriber->tenant_id,
                'invoice_id'     => $invoice->id,
                'subscriber_id'  => $subscriber->id,
                'type'           => $type,
                'label'          => $label,
                'description'    => $bi['description'] ?? null,
                'qty'            => $qty,
                'unit_price'     => $unitPrice,
                'amount'         => $lineAmount,
                'taxable'        => $taxable,
                'tax_rate'       => $taxRate,
                'tax_amount'     => $taxAmount,
                'line_total'     => $lineTotal,
                'is_refundable'  => $isRefund,
                'billing_cycle'  => $type === 'recurring' ? $cycle : null,
                'next_bill_at'   => $nextBillAt,
                'status'         => $bi['status'] ?? ($type === 'recurring' ? 'active' : 'active'),
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Recompute invoice subtotal/tax/amount from its line items.
     */
    protected function recomputeTotals(Invoice $invoice): void
    {
        $items   = $invoice->items;
        $subtotal = $items->sum('amount');
        $taxAmt   = $items->sum('tax_amount');
        $grand    = round($subtotal + $taxAmt, 2);

        $invoice->update([
            'subtotal'   => round($subtotal, 2),
            'tax_amount' => round($taxAmt, 2),
            'amount'     => $grand,
            'total'      => ceil($grand),
        ]);
    }

    /**
     * Pull the default tax rate for the subscriber's plan (falls back to 0).
     */
    protected function resolveTaxRate(Subscriber $subscriber): float
    {
        $plan = $subscriber->plan;
        return $plan?->tax_rate ?? 0.0;
    }

    /**
     * Compute next billing date based on cycle.
     */
    protected function computeNextBillDate(string $cycle): Carbon
    {
        return match ($cycle) {
            'monthly'   => Carbon::now()->addMonth(),
            'quarterly' => Carbon::now()->addMonths(3),
            'yearly'    => Carbon::now()->addYear(),
            default     => Carbon::now()->addMonth(),
        };
    }

    /**
     * Auto-generate invoice number: INV-{YYYYMM}-{4-digit-seq}
     */
    protected function nextInvoiceNumber(int $tenantId): string
    {
        $prefix = date('ym');
        $last = Invoice::where('tenant_id', $tenantId)
            ->where('number', 'like', "INV-{$prefix}-%")
            ->orderByDesc('number')
            ->first();

        $seq = 1;
        if ($last && preg_match('/INV-\d+-(\d+)/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('INV-%s-%04d', $prefix, $seq);
    }
}
