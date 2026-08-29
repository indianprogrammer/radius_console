<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tenant-scoped tax rate managed under Billing & Invoices. Reusable across
 * billing plans; `is_default` selects the rate applied to new plans. A tax may
 * be attached to many plans via the `plan_tax_rate` pivot.
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

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_tax_rate')
            ->withTimestamps();
    }
}
