<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-scoped tax rate managed under Billing & Invoices. Reusable across
 * billing plans; `is_default` selects the rate applied to new plans.
 */
class TaxRate extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'rate', 'type', 'is_default',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_default' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class, 'tax_rate_id');
    }
}
