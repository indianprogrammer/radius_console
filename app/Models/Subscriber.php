<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = [
        'tenant_id', 'username', 'radius_username', 'password_enc', 'mac', 'static_ip',
        'plan_id', 'status', 'kyc_id', 'expiry', 'radius_user_id',
    ];

    protected $hidden = ['password_enc'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
}
