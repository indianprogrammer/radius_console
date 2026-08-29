<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow a plan to have MULTIPLE taxes (or none).
 *
 * Replaces the single `plans.tax_rate_id` FK with a many-to-many pivot
 * `plan_tax_rate`. Both ends are tenant-scoped; the pivot carries `tenant_id`
 * so RLS isolation (SRD §3.1) applies cleanly on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the old single-FK linking (column + constraint). The column was
        // added in an earlier tax migration; guard for both pgsql + sqlite.
        if (Schema::hasColumn('plans', 'tax_rate_id')) {
            if (config('database.default') === 'pgsql') {
                // Drop the FK first, then the column.
                try {
                    DB::statement('ALTER TABLE plans DROP CONSTRAINT IF EXISTS plans_tax_rate_id_foreign');
                } catch (\Throwable $e) {
                    // ignore — constraint may not exist under some states
                }
            }
            Schema::table('plans', function (Blueprint $t) {
                $t->dropColumn('tax_rate_id');
            });
        }

        Schema::create('plan_tax_rate', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $t->foreignId('tax_rate_id')->constrained('tax_rates')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['plan_id', 'tax_rate_id']);
        });

        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE plan_tax_rate ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_plan_tax_rate ON plan_tax_rate USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_tax_rate');

        // Restore the single FK (nullable) for rollback safety.
        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedBigInteger('tax_rate_id')->nullable();
            $t->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
        });
    }
};
