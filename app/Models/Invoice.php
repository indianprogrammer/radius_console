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
        'tax_rate', 'subtotal', 'tax_amount',
    ];

    protected $casts = [
        'amount' => 'float',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'tax_rate' => 'float',
        'due_date' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function subscriber() { return $this->belongsTo(Subscriber::class); }

    /**
     * Compute invoice totals from a pre-tax subtotal and a tax rate (%).
     * Rounds tax to 2 decimals and derives the grand total accordingly.
     */
    public static function computeTotals(float $subtotal, float $taxRate): array
    {
        $subtotal = round($subtotal, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        return [
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'amount' => round($subtotal + $taxAmount, 2),
        ];
    }
}
