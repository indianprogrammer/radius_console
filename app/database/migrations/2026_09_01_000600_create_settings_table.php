<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant settings — the "Settings" menu group (SRD §5.0 #2 "Settings").
 *
 * A single key/value table rather than one column per preference: the console
 * grows new switches constantly and a wide `tenant_settings` table would need a
 * migration for each. Keys are namespaced by section (`billing.currency`), the
 * catalogue of keys + defaults + input types lives in `App\Models\Setting`, and
 * anything absent here simply falls back to that default.
 *
 * Identity fields that already exist on `tenants` (name, logo, theme, domain)
 * are NOT duplicated here — the controller writes those straight to the tenant
 * row so `ResolveTenant` / the layout keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $t->string('key', 100);      // "section.name", e.g. billing.currency
            $t->text('value')->nullable();

            $t->timestamps();

            $t->unique(['tenant_id', 'key']);
        });

        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE settings ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_settings ON settings USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_settings ON settings');
        }

        Schema::dropIfExists('settings');
    }
};
