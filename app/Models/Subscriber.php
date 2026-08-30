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

        // Billing Information
        'billing_type', 'gstin', 'installation_amount', 'security_deposit',
        'po_number', 'po_date',

        // Network Information
        'ip_mode', 'pool_name', 'node_id', 'pop_id', 'switch_id',
        'switch_port', 'connection_type', 'cable_length', 'domain',
        'auth_protocol', 'auto_renew', 'bind_mac', 'bind_static_ip',
        'exclude_mac_bind', 'dont_suspend', 'circuit_id',

        // Location Information
        'country', 'state', 'city', 'zip', 'door_no', 'area', 'colony',
        'building', 'billing_address', 'installation_address',
        'house_type', 'connection_location', 'latitude', 'longitude',

        // Payments
        'advance_payment', 'payment_ref_no', 'payment_type', 'payment_comment',

        // Special discounts / additional charges (JSON: [{reason,desc,approved_by,amount,type}])
        'special_charges',

        // Dynamic billing items (JSON: [{label,type,amount,qty,taxable,billing_cycle,is_refundable,product_id}])
        'billing_items',
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
        'cable_length'       => 'integer',
        'advance_payment'    => 'decimal:2',
        'latitude'           => 'float',
        'longitude'          => 'float',
        'special_charges'    => 'array',
        'billing_items'      => 'array',
    ];

    protected $hidden = ['password_enc'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function bandwidthProfile() { return $this->belongsTo(BandwidthProfile::class); }
}
