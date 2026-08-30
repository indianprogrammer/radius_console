<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Billing-only plan. Network behaviour lives in the linked BandwidthProfile
 * (synced to RADIUS). Laravel relations kept for convenience. A plan may have
 * MANY managed tax rates (or none) via the `plan_tax_rate` pivot.
 */
class Plan extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'price', 'total', 'duration', 'duration_unit',
        'bandwidth_profile_id', 'data_limit_gb',
    ];

    protected $casts = [
        'price' => 'float',
        'total' => 'float',
        'duration' => 'integer',
        'data_limit_gb' => 'integer',
    ];

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'plan_tax_rate')
            ->withTimestamps();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bandwidthProfile(): BelongsTo
    {
        return $this->belongsTo(BandwidthProfile::class);
    }
}
