<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line item on an invoice. Frozen at invoice-time so historical
 * records remain accurate regardless of future product/plan/tax changes.
 */
class InvoiceItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'subscriber_id',
        'type',             // refundable | one-time | recurring
        'label',
        'description',
        'qty',
        'unit_price',
        'amount',           // qty * unit_price
        'taxable',
        'tax_rate',
        'tax_amount',
        'line_total',       // amount + tax_amount
        'is_refundable',
        'billing_cycle',    // monthly | quarterly | yearly | null
        'next_bill_at',
        'status',           // active | inactive
    ];

    protected $casts = [
        'qty'          => 'integer',
        'unit_price'   => 'float',
        'amount'       => 'float',
        'taxable'      => 'boolean',
        'tax_rate'     => 'float',
        'tax_amount'   => 'float',
        'line_total'   => 'float',
        'is_refundable'=> 'boolean',
        'next_bill_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Mark this recurring item as billed, advance next_bill_at.
     */
    public function advanceBillingCycle(): void
    {
        if ($this->type !== 'recurring' || empty($this->billing_cycle)) {
            return;
        }

        $intervalMap = [
            'monthly'   => 'addMonth',
            'quarterly' => 'addMonths(3)',
            'yearly'    => 'addYear',
        ];

        $method = $intervalMap[$this->billing_cycle] ?? 'addMonth';
        $next = $this->next_bill_at ? \Carbon\Carbon::parse($this->next_bill_at)->$method : now()->$method;

        $this->update(['next_bill_at' => $next]);
    }
}
