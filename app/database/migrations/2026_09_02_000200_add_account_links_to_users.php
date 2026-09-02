<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Console identities. The existing users table is the authentication source
 * for every portal role; these optional links identify the business record
 * represented by a franchise, staff member or subscriber account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 30)->default('staff')->change();
            $t->foreignId('franchise_id')->nullable()->after('tenant_id')->constrained('franchises')->nullOnDelete();
            $t->foreignId('staff_id')->nullable()->after('franchise_id')->constrained('staff')->nullOnDelete();
            $t->foreignId('subscriber_id')->nullable()->after('staff_id')->constrained('subscribers')->nullOnDelete();
            $t->boolean('is_active')->default(true)->after('theme_pref');
            $t->timestamp('last_login_at')->nullable()->after('is_active');
            $t->index(['tenant_id', 'role', 'is_active']);
        });

        if (config('database.default') === 'pgsql') {
            \DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'role', 'is_active']);
            $t->dropConstrainedForeignId(['franchise_id', 'staff_id', 'subscriber_id']);
            $t->dropColumn(['is_active', 'last_login_at']);
        });
    }
};
