<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an editable total-bandwidth (data cap) field to plans.
 *
 * Plans already link to a RADIUS bandwidth profile, but the operator may want
 * a plan-level data allowance independent of the profile. `data_limit_gb` is
 * nullable: NULL means unlimited / not set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedInteger('data_limit_gb')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn('data_limit_gb');
        });
    }
};
