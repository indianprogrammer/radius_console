<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a Quotation / Proforma Invoice into a real Invoice.
 *
 * This is the ONLY place a quote becomes a receivable, so the rules live in one
 * spot: the source document must be convertible, its line items are copied as
 * frozen `invoice_items`, and the quote is then locked by recording
 * `converted_invoice_id`. Conversion is wrapped in a transaction because a
 * half-converted document (invoice created, quote not linked) would be
 * invoiceable twice.
 */
final class QuoteService
{
    /**
     * @throws \RuntimeException when the document may not be converted.
     */
    public function convertToInvoice(Quote $quote): Invoice
    {
        if ($quote->isLocked()) {
            throw new \RuntimeException("{$quote->typeLabel()} {$quote->number} has already been converted to an invoice.");
        }

        if (!$quote->isConvertible()) {
            throw new \RuntimeException("{$quote->typeLabel()} {$quote->number} is {$quote->statusLabel()} and cannot be converted.");
        }

        if ($quote->subscriber_id === null) {
            // `invoices.subscriber_id` is NOT NULL — a prospect must be onboarded
            // before they can be billed, which is the correct business order.
            throw new \RuntimeException("{$quote->typeLabel()} {$quote->number} has no linked subscriber. Create the subscriber first, then attach it to the document.");
        }

        if ($quote->items()->count() === 0) {
            throw new \RuntimeException("{$quote->typeLabel()} {$quote->number} has no line items.");
        }

        return DB::transaction(function () use ($quote) {
            $quote->recomputeTotals();

            $invoice = Invoice::create([
                'tenant_id'     => $quote->tenant_id,
                'subscriber_id' => $quote->subscriber_id,
                'number'        => $this->nextInvoiceNumber($quote->tenant_id),
                'status'        => 'unpaid',
                'due_date'      => Carbon::now()
                    ->addDays(Setting::int('billing.invoice_due_days', $quote->tenant_id))
                    ->toDateString(),
                // Totals are recomputed from the copied items below; seed at zero
                // because `invoices.amount` is NOT NULL.
                'subtotal'   => 0,
                'tax_amount' => 0,
                'amount'     => 0,
                'total'      => 0,
                'notes'      => $this->invoiceNote($quote),
            ]);

            foreach ($quote->items as $item) {
                InvoiceItem::create([
                    'tenant_id'     => $quote->tenant_id,
                    'invoice_id'    => $invoice->id,
                    'subscriber_id' => $quote->subscriber_id,
                    // Quote lines have no recurring schedule; everything a quote
                    // sells is a one-off charge on the resulting invoice.
                    'type'          => 'one-time',
                    'label'         => $item->label,
                    'description'   => $item->description,
                    'qty'           => $item->qty,
                    'unit_price'    => $item->unit_price,
                    'amount'        => $item->amount,
                    'taxable'       => $item->taxable,
                    'tax_rate'      => $item->tax_rate,
                    'tax_amount'    => $item->tax_amount,
                    'line_total'    => $item->line_total,
                    'is_refundable' => false,
                    'status'        => 'active',
                ]);
            }

            $this->copyDiscountLine($invoice, $quote);
            $this->applyTotals($invoice, $quote);

            $quote->forceFill([
                'status'               => 'converted',
                'converted_invoice_id' => $invoice->id,
                'converted_at'         => now(),
            ])->save();

            return $invoice->fresh(['items']);
        });
    }

    /**
     * Carry a document-level discount across as its own negative line.
     *
     * `invoices` has no discount column. Folding the discount into the header
     * subtotal alone would leave the line items summing to MORE than the
     * invoice total, which is indefensible on a document the customer reads.
     * An explicit negative line keeps items and header reconciled and shows the
     * customer the concession they were given.
     */
    private function copyDiscountLine(Invoice $invoice, Quote $quote): void
    {
        $discount = round((float) $quote->discount_amount, 2);
        if ($discount <= 0) {
            return;
        }

        InvoiceItem::create([
            'tenant_id'     => $quote->tenant_id,
            'invoice_id'    => $invoice->id,
            'subscriber_id' => $quote->subscriber_id,
            'type'          => 'one-time',
            'label'         => 'Discount',
            'description'   => "As agreed on {$quote->typeLabel()} {$quote->number}.",
            'qty'           => 1,
            'unit_price'    => -$discount,
            'amount'        => -$discount,
            // The discount applies to the pre-tax subtotal, so it carries no tax
            // of its own — the per-line tax was already snapshotted above.
            'taxable'       => false,
            'tax_rate'      => 0,
            'tax_amount'    => 0,
            'line_total'    => -$discount,
            'is_refundable' => false,
            'status'        => 'active',
        ]);
    }

    /**
     * Copy the document's totals onto the invoice.
     *
     * The discount is subtracted from the stored subtotal (and mirrored as a
     * negative line item by `copyDiscountLine()`), so the invoice never bills
     * more than the customer accepted. The rounding preference is shared with
     * InvoiceService.
     */
    private function applyTotals(Invoice $invoice, Quote $quote): void
    {
        $subtotal = round($quote->subtotal - $quote->discount_amount, 2);
        $amount = round($subtotal + $quote->tax_amount, 2);
        $roundUp = Setting::bool('billing.round_invoice_total', $quote->tenant_id);

        $invoice->update([
            'subtotal'   => $subtotal,
            'tax_amount' => round($quote->tax_amount, 2),
            'amount'     => $amount,
            'total'      => $roundUp ? ceil($amount) : $amount,
        ]);
    }

    /** Provenance line so the invoice records where it came from. */
    private function invoiceNote(Quote $quote): string
    {
        $note = "Converted from {$quote->typeLabel()} {$quote->number}.";

        return $quote->notes ? $note . ' ' . $quote->notes : $note;
    }

    /**
     * Mirrors InvoiceService::nextInvoiceNumber() — same prefix setting and the
     * same per-month series, so both creation paths share one numbering scheme.
     */
    private function nextInvoiceNumber(int|string $tenantId): string
    {
        $prefix = trim(Setting::get('billing.invoice_prefix', $tenantId)) ?: 'INV';
        $month = date('ym');

        $last = Invoice::where('tenant_id', $tenantId)
            ->where('number', 'like', "{$prefix}-{$month}-%")
            ->orderByDesc('number')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $month, $seq);
    }
}
