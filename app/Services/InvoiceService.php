<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
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
     * Creates one invoice (or re-uses an existing unpaid one) based on the
     * subscriber's plan and associated tax rates.
     *
     * @param  Subscriber  $subscriber
     * @return Invoice
     */
    public function generateFromSubscriber(Subscriber $subscriber): Invoice
    {
        $invoice = $this->getOrCreateInvoice($subscriber);

        // Sync items based on the subscriber's plan (price, tax, duration)
        $this->syncItems($invoice, $subscriber);

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
                // Payment terms come from Settings > Billing.
                'due_date'      => Carbon::now()
                    ->addDays(Setting::int('billing.invoice_due_days', $subscriber->tenant_id))
                    ->toDateString(),
                // `amount` is NOT NULL; seed the money columns at zero and let
                // recomputeTotals() fill them once line items are attached.
                'subtotal'      => 0,
                'tax_amount'    => 0,
                'amount'        => 0,
                'total'         => 0,
            ]);
        }

        return $invoice;
    }

    /**
     * Sync line items on the invoice from the subscriber's plan and tax settings.
     *
     * When no explicit billing items are supplied (the column has been removed),
     * each active plan item is represented as a single one-time line.
     */
    protected function syncItems(Invoice $invoice, Subscriber $subscriber): Invoice
    {
        // Remove any existing items first
        $invoice->items()->delete();

        $plan = $subscriber->plan;
        if (! $plan) {
            return $invoice->fresh();
        }

        $taxRate = $this->resolveTaxRate($subscriber);
        $price   = (float) ($plan->price ?? 0);

        InvoiceItem::create([
            'tenant_id'      => $subscriber->tenant_id,
            'invoice_id'     => $invoice->id,
            'subscriber_id'  => $subscriber->id,
            'type'           => 'one-time',
            'label'          => $plan->name,
            'description'    => null,
            'qty'            => 1,
            'unit_price'     => $price,
            'amount'         => $price,
            'taxable'        => true,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxRate ? round($price * $taxRate / 100, 2) : 0,
            'line_total'     => $taxRate ? round($price + $price * $taxRate / 100, 2) : $price,
            'is_refundable'  => false,
            'billing_cycle'  => null,
            'next_bill_at'   => null,
            'status'         => 'active',
        ]);

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

        // Settings > Billing decides whether the payable total is rounded up to
        // the next whole unit (common for cash collection) or kept exact.
        $roundUp = Setting::bool('billing.round_invoice_total', $invoice->tenant_id);

        $invoice->update([
            'subtotal'   => round($subtotal, 2),
            'tax_amount' => round($taxAmt, 2),
            'amount'     => $grand,
            'total'      => $roundUp ? ceil($grand) : $grand,
        ]);
    }

    /**
     * Effective tax percentage for the subscriber's plan.
     *
     * Plans carry MANY tax rates via the `plan_tax_rate` pivot (the old
     * `plans.tax_rate` column was dropped), so the percentage rates are summed.
     * Only percentage rates are representable on a line item's `tax_rate`;
     * fixed-amount taxes are a plan-level charge and are skipped here.
     */
    protected function resolveTaxRate(Subscriber $subscriber): float
    {
        $plan = $subscriber->plan;
        if ($plan === null) {
            return 0.0;
        }

        return round((float) $plan->taxes
            ->where('type', '!=', 'fixed')
            ->sum('rate'), 2);
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
     * Auto-generate invoice number: {PREFIX}-{YYMM}-{4-digit-seq}
     *
     * The prefix comes from Settings > Billing; the running sequence is scoped
     * to that prefix + month, so changing the prefix starts a fresh series
     * instead of colliding with existing numbers.
     */
    protected function nextInvoiceNumber(int $tenantId): string
    {
        $settingPrefix = trim(Setting::get('billing.invoice_prefix', $tenantId)) ?: 'INV';
        $month = date('ym');

        $last = Invoice::where('tenant_id', $tenantId)
            ->where('number', 'like', "{$settingPrefix}-{$month}-%")
            ->orderByDesc('number')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $settingPrefix, $month, $seq);
    }
}
