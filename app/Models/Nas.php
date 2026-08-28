<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nas extends Model
{
    protected $fillable = ['tenant_id', 'name', 'nas_ip', 'shared_secret', 'nas_identifier', 'type', 'api_enabled', 'api_host', 'api_port', 'api_username', 'api_password', 'description', 'radius_nas_id'];

    protected $casts = [
        'api_enabled' => 'boolean',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
