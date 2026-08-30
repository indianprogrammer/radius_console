<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the subscriber access type (PPPoE / IPoE) plus the PPPoE credential
 * pair that is only relevant when `access_type` = 'pppoe'.
 *
 *   access_type     string  – 'pppoe' (default) | 'ipoe'
 *   pppoe_username  string  – PPPoE login presented by the CPE
 *   pppoe_password  string  – PPPoE secret (needed in clear for CHAP)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->string('access_type', 10)->default('pppoe')->after('father_or_company');
            $t->string('pppoe_username', 64)->nullable()->after('access_type');
            $t->string('pppoe_password', 128)->nullable()->after('pppoe_username');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn(['access_type', 'pppoe_username', 'pppoe_password']);
        });
    }
};
