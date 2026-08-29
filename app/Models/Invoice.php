<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped invoice. `amount` is the GRAND TOTAL (subtotal + tax).
 * `subtotal`/`tax_amount`/`tax_rate` capture the breakdown at invoice time so
 * historical invoices remain correct even if the plan's tax rate changes.
 */
class Invoice extends Model
{
    protected $fillable = [
        'tenant_id', 'subscriber_id', 'number', 'amount', 'status', 'due_date',
        'tax_rate', 'subtotal', 'tax_amount', 'total',
    ];

    protected $casts = [
        'amount' => 'float',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'tax_rate' => 'float',
        'total' => 'float',
        'due_date' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function subscriber() { return $this->belongsTo(Subscriber::class); }

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
