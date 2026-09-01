<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on a Quotation / Proforma Invoice.
 *
 * The label, price and tax are SNAPSHOTTED even when `product_id` is set, so
 * editing the product catalogue later cannot silently rewrite a document that
 * has already been sent to a customer.
 */
class QuoteItem extends Model
{
    protected $fillable = [
        'tenant_id', 'quote_id', 'product_id',
        'label', 'description',
        'qty', 'unit_price', 'amount',
        'taxable', 'tax_rate', 'tax_amount', 'line_total',
        'sort_order',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'unit_price' => 'float',
        'amount'     => 'float',
        'taxable'    => 'boolean',
        'tax_rate'   => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /**
     * Derive amount / tax / line total from qty, unit price and tax rate.
     *
     * Returns the computed columns rather than mutating, so the caller can use
     * it for both create and update paths.
     */
    public static function computeLine(float $unitPrice, int $qty, bool $taxable, float $taxRate): array
    {
        $qty = max(1, $qty);
        $amount = round($unitPrice * $qty, 2);
        $taxAmount = $taxable ? round($amount * $taxRate / 100, 2) : 0.0;

        return [
            'qty'        => $qty,
            'unit_price' => round($unitPrice, 2),
            'amount'     => $amount,
            'taxable'    => $taxable,
            'tax_rate'   => $taxable ? round($taxRate, 2) : 0.0,
            'tax_amount' => $taxAmount,
            'line_total' => round($amount + $taxAmount, 2),
        ];
    }
}
