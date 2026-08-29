<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bandwidth_profiles: rename tenant_id → company_id; rename
 * radius_profile_id → radius_plan_id so the column name matches the
 * RADIUS /api/plans endpoint and id type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bandwidth_profiles', function (Blueprint $t) {
            $t->renameColumn('tenant_id', 'company_id');
        });

        Schema::table('bandwidth_profiles', function (Blueprint $t) {
            $t->renameColumn('radius_profile_id', 'radius_plan_id');
        });

        // Update RLS policy column name
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_bandwidth_profiles ON bandwidth_profiles');
            DB::statement("CREATE POLICY tenant_isolation_bandwidth_profiles ON bandwidth_profiles USING (company_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_bandwidth_profiles ON bandwidth_profiles');
            DB::statement("CREATE POLICY tenant_isolation_bandwidth_profiles ON bandwidth_profiles USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
        Schema::table('bandwidth_profiles', function (Blueprint $t) {
            $t->renameColumn('radius_plan_id', 'radius_profile_id');
        });
        Schema::table('bandwidth_profiles', function (Blueprint $t) {
            $t->renameColumn('company_id', 'tenant_id');
        });
    }
};
