<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-scoped invoice. `amount` is the GRAND TOTAL (subtotal + tax).
 * `subtotal`/`tax_amount`/`tax_rate` capture the breakdown at invoice time so
 * historical invoices remain correct even if the plan's tax rate changes.
 *
 * `paid_amount` mirrors the sum of completed payments and drives `status`
 * (unpaid -> partial -> paid). `void` is set manually and freezes the invoice.
 */
class Invoice extends Model
{
    public const STATUSES = [
        'draft'   => 'Draft',
        'unpaid'  => 'Unpaid',
        'partial' => 'Partially Paid',
        'paid'    => 'Paid',
        'void'    => 'Void',
    ];

    protected $fillable = [
        'tenant_id', 'subscriber_id', 'number', 'amount', 'status', 'due_date',
        'tax_rate', 'subtotal', 'tax_amount', 'total', 'paid_amount', 'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'tax_rate' => 'float',
        'total' => 'float',
        'paid_amount' => 'float',
        'due_date' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }

    /** Charged figure — the rounded `total` when present, else `amount`. */
    public function payableAmount(): float
    {
        return (float) ($this->total ?? $this->amount ?? 0);
    }

    /** Outstanding balance (never negative). */
    public function balance(): float
    {
        return max(0, round($this->payableAmount() - (float) $this->paid_amount, 2));
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->balance() > 0
            && ! in_array($this->status, ['void', 'paid'], true)
            && $this->due_date->isPast();
    }

    /**
     * Recompute `paid_amount` from completed payments and derive the status.
     * Void invoices keep their status (they are excluded from collection).
     */
    public function refreshStatus(): self
    {
        $paid = round((float) $this->payments()->where('status', 'completed')->sum('amount'), 2);
        $payable = $this->payableAmount();

        $status = $this->status;
        if ($status !== 'void') {
            $status = match (true) {
                $paid <= 0        => $this->status === 'draft' ? 'draft' : 'unpaid',
                $paid >= $payable => 'paid',
                default           => 'partial',
            };
        }

        $this->forceFill(['paid_amount' => $paid, 'status' => $status])->save();

        return $this;
    }

    /**
     * Compute invoice totals from a pre-tax subtotal and a tax amount.
     * `amount` is the precise total; `total` is the ceiling-rounded figure
     * persisted for consistency (never undercharges).
     */
    public static function computeTotals(float $subtotal, float $taxAmount): array
    {
        $subtotal = round($subtotal, 2);
        $taxAmount = round($taxAmount, 2);
        $amount = round($subtotal + $taxAmount, 2);
        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'amount' => $amount,
            'total' => ceil($amount),
        ];
    }
}
