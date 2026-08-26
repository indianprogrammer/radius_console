<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant (ISP). Has NO tenant_id column (SRD §8 note) — it is the root
 * of the multi-tenant hierarchy. Row isolation for other tables is keyed on tenant_id.
 */
class Tenant extends Model
{
    protected $fillable = ['name', 'domain', 'slug', 'theme_default', 'logo_url', 'status'];

    public function subscribers() { return $this->hasMany(Subscriber::class); }
    public function plans() { return $this->hasMany(Plan::class); }
}
