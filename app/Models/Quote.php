<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Quotation or a Proforma Invoice.
 *
 * Both are PRE-SALE documents and are deliberately NOT receivables: they never
 * appear in the ledger, the collection totals or the payments screens. Money
 * only becomes real when `QuoteService::convertToInvoice()` turns the document
 * into an `Invoice`, after which `converted_invoice_id` is set and the document
 * is frozen (`isLocked()`).
 *
 * The two types share this model because they differ only in numbering series
 * and vocabulary; see the quotes migration for the reasoning.
 */
class Quote extends Model
{
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_PROFORMA  = 'proforma';

    public const TYPES = [
        self::TYPE_QUOTATION => 'Quotation',
        self::TYPE_PROFORMA  => 'Proforma Invoice',
    ];

    /** Number prefix per type, e.g. QTN-2609-0001 / PRO-2609-0001. */
    public const PREFIXES = [
        self::TYPE_QUOTATION => 'QTN',
        self::TYPE_PROFORMA  => 'PRO',
    ];

    /**
     * Lifecycle. A quotation is accepted or declined by the customer; a
     * proforma is issued and then either paid (→ converted) or expires. Both
     * share the vocabulary so one status column serves both.
     */
    public const STATUSES = [
        'draft'     => 'Draft',
        'sent'      => 'Sent',
        'accepted'  => 'Accepted',
        'declined'  => 'Declined',
        'expired'   => 'Expired',
        'converted' => 'Converted',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses from which the document may still be converted to an invoice. */
    public const CONVERTIBLE_STATUSES = ['draft', 'sent', 'accepted'];

    protected $fillable = [
        'tenant_id', 'type', 'number', 'status',
        'subscriber_id',
        'customer_name', 'customer_email', 'customer_phone',
        'customer_address', 'customer_gstin',
        'issue_date', 'valid_until',
        'subtotal', 'discount_amount', 'tax_amount', 'amount', 'total',
        'notes', 'terms',
        'converted_invoice_id', 'converted_at',
    ];

    protected $casts = [
        'issue_date'      => 'date',
        'valid_until'     => 'date',
        'converted_at'    => 'datetime',
        'subtotal'        => 'float',
        'discount_amount' => 'float',
        'tax_amount'      => 'float',
        'amount'          => 'float',
        'total'           => 'float',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }

    /** The invoice this document became, if it was converted. */
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'converted_invoice_id'); }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Display name: the linked subscriber wins, else the free-text prospect. */
    public function customerLabel(): string
    {
        if ($this->subscriber) {
            return $this->subscriber->username;
        }

        return $this->customer_name ?: '—';
    }

    /** A converted document is immutable — it is now represented by an invoice. */
    public function isLocked(): bool
    {
        return $this->converted_invoice_id !== null;
    }

    public function isConvertible(): bool
    {
        return !$this->isLocked() && in_array($this->status, self::CONVERTIBLE_STATUSES, true);
    }

    /**
     * Past its validity date and still awaiting a decision.
     *
     * Derived rather than stored so a document becomes stale on its own without
     * needing a scheduled job; `status` is only set to `expired` if a user acts.
     */
    public function isExpired(): bool
    {
        return $this->valid_until !== null
            && !$this->isLocked()
            && in_array($this->status, ['draft', 'sent'], true)
            && $this->valid_until->endOfDay()->isPast();
    }

    /** Colour bucket for the status pill (reuses the billing pill classes). */
    public function statusPill(): string
    {
        return match ($this->status) {
            'accepted', 'converted' => 'paid',
            'sent'                  => 'partial',
            'declined', 'expired'   => 'unpaid',
            'cancelled'             => 'void',
            default                 => 'draft',
        };
    }

    /**
     * Recompute the money columns from the line items.
     *
     * The discount is applied to the pre-tax subtotal; item tax is already
     * snapshotted per line, so it is summed rather than recomputed here.
     */
    public function recomputeTotals(): self
    {
        $items = $this->items()->get();

        $subtotal = round((float) $items->sum('amount'), 2);
        $taxAmount = round((float) $items->sum('tax_amount'), 2);
        // Never discount below zero.
        $discount = min(round((float) $this->discount_amount, 2), $subtotal);
        $amount = round($subtotal - $discount + $taxAmount, 2);

        $this->forceFill([
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'tax_amount'      => $taxAmount,
            'amount'          => $amount,
            'total'           => ceil($amount),
        ])->save();

        return $this;
    }

    public function payableAmount(): float
    {
        return (float) ($this->total ?: $this->amount);
    }

    /**
     * Next number for a tenant + type: QTN-2609-0001.
     *
     * Series are per type and per month. MAX on the numeric tail via an id sort
     * rather than a string sort on `number`, so 0009 → 0010 stays in order once
     * the tail widens.
     */
    public static function nextNumber(int|string $tenantId, string $type): string
    {
        $prefix = self::PREFIXES[$type] ?? 'QTN';
        $month = date('ym');

        $last = self::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('number', 'like', "{$prefix}-{$month}-%")
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $month, $seq);
    }
}
