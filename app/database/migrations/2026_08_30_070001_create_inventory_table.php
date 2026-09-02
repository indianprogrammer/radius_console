<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock items — Billing & Invoices.
 *
 * Deliberately separate from `products`: a product is a priced catalogue entry
 * used on invoices, whereas an inventory row tracks a countable quantity with a
 * reorder threshold and a cost/sale spread. Merging them would put nullable
 * stock columns on every service line.
 *
 * Table is the singular `inventory` (see Inventory::$table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('sku', 100);
            $t->string('name', 200);
            $t->text('description')->nullable();
            $t->enum('category', ['physical', 'digital', 'service', 'accessory'])->default('physical');
            $t->string('unit', 30)->default('pcs');   // pcs, units, licenses, etc.
            $t->decimal('stock_quantity', 10, 2)->default(0);
            $t->decimal('reorder_point', 10, 2)->default(0);
            $t->decimal('cost_price', 12, 2)->default(0);
            $t->decimal('sale_price', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            // Per-tenant, not global: two tenants may legitimately use the same SKU.
            $t->unique(['tenant_id', 'sku']);
            $t->index(['tenant_id', 'category', 'is_active']);
        });

        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE inventory ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_inventory ON inventory USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_inventory ON inventory');
        }

        Schema::dropIfExists('inventory');
    }
};