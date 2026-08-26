<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'price', 'cycle', 'download_mbps', 'upload_mbps',
        'data_limit_gb', 'duration_days', 'fup_threshold_gb', 'fup_download_mbps',
        'fup_upload_mbps', 'simultaneous_use', 'radius_profile_id',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
