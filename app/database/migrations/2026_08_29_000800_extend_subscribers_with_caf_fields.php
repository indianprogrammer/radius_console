<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the `subscribers` table with the full Customer Application Form
 * (CAF) columns referenced in the legacy PHP ISP form (Basic, Billing,
 * Network, Location, Payments, Specials).
 *
 * All new columns are nullable so existing rows keep working. JSON column
 * `special_charges` stores the dynamic "Special Discount / Additional
 * Charges" repeater rows.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            // Basic Information
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('father_or_company')->nullable();
            $t->string('mobile')->nullable();
            $t->string('email')->nullable();

            // Billing Information
            $t->unsignedTinyInteger('billing_type')->nullable();
            $t->string('gstin')->nullable();
            $t->decimal('installation_amount', 10, 2)->nullable();
            $t->decimal('security_deposit', 10, 2)->nullable();
            $t->string('po_number')->nullable();
            $t->timestamp('po_date')->nullable();

            // Network Information
            $t->unsignedTinyInteger('ip_mode')->nullable();
            $t->string('pool_name')->nullable();
            $t->string('node_id')->nullable();
            $t->string('pop_id')->nullable();
            $t->string('switch_id')->nullable();
            $t->string('switch_port')->nullable();
            $t->unsignedTinyInteger('connection_type')->nullable();
            $t->unsignedInteger('cable_length')->nullable();
            $t->string('domain')->nullable();
            $t->unsignedTinyInteger('auth_protocol')->nullable();
            $t->boolean('auto_renew')->default(false);
            $t->boolean('bind_mac')->default(false);
            $t->boolean('bind_static_ip')->default(false);
            $t->boolean('exclude_mac_bind')->default(false);
            $t->boolean('dont_suspend')->default(false);
            $t->string('circuit_id')->nullable();

            // Location Information
            $t->string('country')->nullable();
            $t->string('state')->nullable();
            $t->string('city')->nullable();
            $t->string('zip')->nullable();
            $t->string('door_no')->nullable();
            $t->string('area')->nullable();
            $t->string('colony')->nullable();
            $t->string('building')->nullable();
            $t->text('billing_address')->nullable();
            $t->text('installation_address')->nullable();
            $t->string('house_type')->nullable();
            $t->string('connection_location')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();

            // Payments
            $t->decimal('advance_payment', 10, 2)->nullable();
            $t->string('payment_ref_no')->nullable();
            $t->unsignedTinyInteger('payment_type')->nullable();
            $t->string('payment_comment')->nullable();

            // Special Discount / Additional Charges (repeater rows)
            $t->json('special_charges')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $cols = [
                'first_name','last_name','father_or_company','mobile','email',
                'billing_type','gstin',
                'installation_amount','security_deposit','po_number','po_date',
                'ip_mode','pool_name','node_id','pop_id','switch_id','switch_port',
                'connection_type','cable_length','domain','auth_protocol',
                'auto_renew','bind_mac','bind_static_ip','exclude_mac_bind',
                'dont_suspend','circuit_id',
                'country','state','city','zip','door_no','area','colony','building',
                'billing_address','installation_address','house_type',
                'connection_location','latitude','longitude',
                'advance_payment','payment_ref_no','payment_type','payment_comment',
                'special_charges',
            ];
            $t->dropColumn($cols);
        });
    }
};
