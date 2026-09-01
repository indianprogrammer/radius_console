<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Franchises / LCOs — Franchise Management (SRD §5.0 #3, §5.4).
 *
 * A franchise is a tenant-scoped reseller in the LCO hierarchy. `parent_id`
 * is self-referencing so a franchise can sit under a distributor
 * (Super Admin -> ISP Admin -> LCO -> ...).
 *
 * `balance` is the prepaid wallet figure (§5.4) and is system-maintained —
 * it is seeded from the "Opening Balance" entered at creation time and is not
 * editable from the edit form. `credit_limit` backs the per-LCO overdraft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('parent_id')->nullable()->constrained('franchises')->nullOnDelete();

            $t->string('code', 40);                            // unique per tenant
            $t->string('name', 150);
            $t->string('type', 20)->default('franchise');      // franchise|lco|distributor

            $t->string('contact_person', 150)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 20)->nullable();

            $t->text('address')->nullable();
            $t->string('city', 100)->nullable();
            $t->string('state', 100)->nullable();
            $t->string('pincode', 12)->nullable();

            $t->string('gst_number', 20)->nullable();
            $t->string('pan_number', 20)->nullable();

            $t->string('commission_type', 20)->default('percentage'); // percentage|fixed
            $t->decimal('commission_rate', 8, 2)->default(0);
            $t->decimal('credit_limit', 12, 2)->default(0);
            $t->decimal('balance', 12, 2)->default(0);

            $t->string('status', 20)->default('active');       // active|suspended|inactive
            $t->text('notes')->nullable();

            $t->timestamps();

            $t->unique(['tenant_id', 'code']);
            $t->index(['tenant_id', 'status']);
        });

        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE franchises ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_franchises ON franchises USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_franchises ON franchises');
        }

        Schema::dropIfExists('franchises');
    }
};
