<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bandwidth profiles: the RADIUS-synced, bandwidth-only side of an offering.
 * These columns mirror the external RADIUS "plan/profile" record; RADIUS is
 * the system of record. The financial side lives in `plans` (altered below).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name');
            $t->integer('download_mbps');
            $t->integer('upload_mbps');
            $t->integer('data_limit_gb')->nullable();
            $t->integer('duration_days');
            $t->integer('fup_threshold_gb')->nullable();
            $t->integer('fup_download_mbps')->nullable();
            $t->integer('fup_upload_mbps')->nullable();
            $t->integer('simultaneous_use')->default(1);
            $t->string('radius_profile_id')->nullable();
            $t->timestamps();
        });

        // RLS keyed on tenant_id (defense-in-depth; guardrail is in the repo).
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE bandwidth_profiles ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_bandwidth_profiles ON bandwidth_profiles USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_bandwidth_profiles ON bandwidth_profiles');
        }
        Schema::dropIfExists('bandwidth_profiles');
    }
};
