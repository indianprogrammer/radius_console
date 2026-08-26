<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Tenant (root of hierarchy; NO tenant_id column, SRD §8) ----
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('domain')->unique();
            $t->string('slug')->unique();
            $t->string('theme_default')->default('light'); // light | dark
            $t->string('logo_url')->nullable();
            $t->string('status')->default('active'); // active | suspended
            $t->timestamps();
        });

        // ---- Staff / RBAC users (tenant-scoped) ----
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role')->default('isp_admin'); // super_admin|isp_admin|lco|technician|subscriber
            $t->string('theme_pref')->nullable(); // per-user override
            $t->rememberToken();
            $t->timestamps();
        });

        // ---- Plans (tenant-scoped) ----
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name');
            $t->decimal('price', 10, 2);
            $t->string('cycle'); // monthly|quarterly|yearly
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

        // ---- Subscribers (tenant-scoped) ----
        Schema::create('subscribers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('username')->unique();
            $t->string('radius_username');       // tenant_slug + username namespace
            $t->text('password_enc');            // AES-256-GCM reversibly encrypted (SRD §9.3)
            $t->string('mac')->nullable();
            $t->string('static_ip')->nullable();
            $t->foreignId('plan_id')->nullable()->constrained('plans');
            $t->string('status');                 // active|suspended|expired
            $t->foreignId('kyc_id')->nullable();
            $t->timestamp('expiry')->nullable();
            $t->string('radius_user_id')->nullable();
            $t->timestamps();
        });

        // ---- KYC (tenant-scoped) ----
        Schema::create('kyc', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('subscriber_id')->nullable()->constrained('subscribers');
            $t->string('document_type');
            $t->string('document_number');
            $t->string('verification_status')->default('pending'); // pending|verified|rejected
            $t->timestamps();
        });
        // backfill FK from subscribers.kyc_id -> kyc.id
        Schema::table('subscribers', fn (Blueprint $t) =>
            $t->foreign('kyc_id')->references('id')->on('kyc')->nullOnDelete());

        // ---- Wallet + ledger (tenant-scoped) ----
        Schema::create('wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('subscriber_id')->constrained('subscribers');
            $t->decimal('balance', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('wallet_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('wallet_id')->constrained('wallets');
            $t->string('type'); // credit|debit
            $t->decimal('amount', 12, 2);
            $t->string('reference')->nullable();
            $t->timestamps();
        });

        // ---- Invoices (tenant-scoped) ----
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('subscriber_id')->constrained('subscribers');
            $t->string('number')->unique();
            $t->decimal('amount', 12, 2);
            $t->string('status')->default('unpaid'); // unpaid|paid|void
            $t->timestamp('due_date')->nullable();
            $t->timestamps();
        });

        // ---- NAS devices (tenant-scoped) ----
        Schema::create('nas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name');
            $t->string('ip_address');
            $t->string('radius_nas_id')->nullable();
            $t->timestamps();
        });

        // ---- Session cache (mirror of RADIUS active sessions, tenant-scoped) ----
        Schema::create('sessions_cache', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('radius_session_id');
            $t->string('username');
            $t->string('nas_ip')->nullable();
            $t->bigInteger('input_octets')->default(0);
            $t->bigInteger('output_octets')->default(0);
            $t->timestamp('start_time')->nullable();
            $t->timestamps();
        });

        // ---- Audit log (tenant-scoped) ----
        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('action');
            $t->text('payload')->nullable();
            $t->timestamps();
        });

        $this->applyRls();
    }

    public function down(): void
    {
        $tables = ['audit_log','sessions_cache','invoices','wallet_ledger','wallets','kyc',
                   'subscribers','plans','users','tenants','nas'];
        foreach ($tables as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }

    /**
     * PostgreSQL Row-Level Security. Skipped on SQLite (local boot only).
     * Every tenant table gets a policy keyed on app.current_tenant. SRD §3.1.
     */
    private function applyRls(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
        DB::statement("ALTER TABLE users ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_users ON users USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE plans ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_plans ON plans USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE subscribers ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_subscribers ON subscribers USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE kyc ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_kyc ON kyc USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE wallets ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_wallets ON wallets USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE wallet_ledger ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_wallet_ledger ON wallet_ledger USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE invoices ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_invoices ON invoices USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE nas ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_nas ON nas USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE sessions_cache ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_sessions_cache ON sessions_cache USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        DB::statement("ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY tenant_isolation_audit_log ON audit_log USING (tenant_id = current_setting('app.current_tenant')::bigint)");
    }
};
