<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tenant-scoped tax rate managed under Billing & Invoices. Reusable across
 * billing plans; a tax may be attached to many plans via the `plan_tax_rate`
 * pivot.
 */
class TaxRate extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'rate', 'type',
    ];

    protected $casts = [
        'rate' => 'float',
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
