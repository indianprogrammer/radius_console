<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nas extends Model
{
    protected $fillable = ['tenant_id', 'name', 'nas_ip', 'shared_secret', 'nas_identifier', 'type', 'api_enabled', 'description', 'radius_nas_id'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
}
