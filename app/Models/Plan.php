<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Billing-only plan. Network behaviour lives in the linked BandwidthProfile
 * (synced to RADIUS). Laravel relations kept for convenience.
 */
class Plan extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'price', 'cycle', 'bandwidth_profile_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bandwidthProfile(): BelongsTo
    {
        return $this->belongsTo(BandwidthProfile::class);
    }
}
