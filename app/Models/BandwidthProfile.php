<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local mirror of an external RADIUS bandwidth profile.
 * Bandwidth-only columns; `radius_plan_id` links to the RADIUS /api/plans record
 * (system of record). Financial/billing details live in `Plan`.
 * Scoped by `company_id` per tenant.
 */
class BandwidthProfile extends Model
{
    protected $fillable = [
        'company_id', 'name', 'download_mbps', 'upload_mbps', 'data_limit_gb',
        'duration_days', 'fup_threshold_gb', 'fup_download_mbps', 'fup_upload_mbps',
        'simultaneous_use', 'radius_plan_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
