<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    /**
     * Fillable list mirrors the full CAF (Customer Application Form) and
     * the RADIUS / billing extensions. The legacy `mac` and `static_ip`
     * fields are kept on the form as well, plus the new richer fields below.
     */
    protected $fillable = [
        // Core RADIUS / account
        'tenant_id', 'username', 'radius_username', 'password_enc',
        'mac', 'static_ip', 'plan_id', 'bandwidth_profile_id', 'status',
        'kyc_id', 'expiry', 'radius_user_id',

        // Basic Information
        'first_name', 'last_name',
        'father_or_company', 'mobile', 'email',

        // Access / authentication method
        'access_type', 'pppoe_username', 'pppoe_password',

        // Billing Information
        'billing_type', 'gstin', 'installation_amount', 'security_deposit',
        'po_number', 'po_date',

        // Network Information
        'ip_mode', 'pool_name',
        'auto_renew', 'bind_mac', 'bind_static_ip',
        'exclude_mac_bind', 'dont_suspend',

        // Address — billing side
        'billing_address', 'billing_city', 'billing_state',
        'billing_zip', 'billing_country',

        // Address — installation side (locality + map pin)
        'installation_address', 'installation_same_as_billing',
        'installation_landmark', 'installation_place_label',
        'city', 'state', 'zip', 'country',
        'latitude', 'longitude',

        // Special discounts / additional charges (JSON: [{reason,desc,approved_by,amount,type}])
        'special_charges',
    ];

    protected $casts = [
        'auto_renew'         => 'boolean',
        'bind_mac'           => 'boolean',
        'bind_static_ip'     => 'boolean',
        'exclude_mac_bind'   => 'boolean',
        'dont_suspend'       => 'boolean',
        'expiry'             => 'datetime',
        'po_date'            => 'datetime',
        'installation_amount'=> 'decimal:2',
        'security_deposit'   => 'decimal:2',
        'special_charges'    => 'array',

        'installation_same_as_billing' => 'boolean',
        // Kept as strings, not floats: the map round-trips the exact stored
        // value and float casting would reintroduce binary rounding drift.
        'latitude'           => 'decimal:7',
        'longitude'          => 'decimal:7',
    ];

    protected $hidden = ['password_enc'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function bandwidthProfile() { return $this->belongsTo(BandwidthProfile::class); }
}
