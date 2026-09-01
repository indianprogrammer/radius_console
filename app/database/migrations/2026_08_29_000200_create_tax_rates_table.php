<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax Rates — managed under the Billing & Invoices section.
 *
 * Each tenant defines reusable taxes (e.g. "VAT 18%", "Service Tax 5%") that
 * can be attached to billing plans. `rate` is a percentage (e.g. 18.00).
 * `is_default` marks the rate applied to new plans that pick "default".
 *
 * Tenant-scoped for RLS (SRD §3.1). On PostgreSQL the table gets a tenant
 * isolation policy; on SQLite it is simply filtered by tenant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name');                       // e.g. "VAT", "GST"
            $t->decimal('rate', 5, 2);               // percentage, e.g. 18.00
            $t->string('type')->default('percentage'); // percentage|fixed
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE tax_rates ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_tax_rates ON tax_rates USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }

        // Link plans to tax rates. The column was added in the earlier
        // tax migration; ensure it exists, then add the FK (tax_rates now exists).
        //
        // SQLite is deliberately excluded from the FK: it cannot drop a foreign
        // key clause, so once `tax_rate_id` is removed by the later
        // `plans_many_taxes_pivot` migration the table definition keeps a
        // dangling FK and EVERY subsequent `ALTER TABLE plans DROP COLUMN`
        // fails with "unknown column tax_rate_id in foreign key definition".
        // The column itself is short-lived (replaced by the plan_tax_rate
        // pivot), so skipping the constraint on SQLite costs nothing.
        Schema::table('plans', function (Blueprint $t) {
            if (!Schema::hasColumn('plans', 'tax_rate_id')) {
                $t->unsignedBigInteger('tax_rate_id')->nullable();
            }
            if (config('database.default') !== 'sqlite') {
                $t->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
