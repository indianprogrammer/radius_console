<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reshape the local `nas` mirror to match the external RADIUS /api/nas schema
 * (SRD §4.2). RADIUS requires `nas_ip` + `shared_secret`; we also persist the
 * RADIUS-assigned `radius_nas_id` for reconciliation.
 *
 * SQLite cannot drop columns in-place, so drop + recreate (table is dev-empty).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('nas');

        Schema::create('nas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name')->nullable();          // friendly label (defaults to nas_ip)
            $t->string('nas_ip');                    // REQUIRED by RADIUS
            $t->string('shared_secret');             // REQUIRED by RADIUS (RADIUS/CoA secret)
            $t->string('nas_identifier')->nullable();
            $t->string('type')->nullable();          // mikrotik | cisco | ...
            $t->boolean('api_enabled')->default(false);
            $t->text('description')->nullable();
            $t->integer('radius_nas_id')->nullable(); // id returned by RADIUS on create
            $t->timestamps();
        });

        // The dropIfExists above stripped RLS applied in 000000; re-enable it.
        // PostgreSQL only; skipped on SQLite. SRD §3.1.
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE nas ENABLE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_isolation_nas ON nas USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nas');
    }
};
