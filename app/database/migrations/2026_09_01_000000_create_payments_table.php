<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payments received against invoices (Billing & Invoices §5.5).
 *
 * A payment is always tenant + subscriber scoped. `invoice_id` is nullable so
 * an on-account / advance receipt can be recorded before an invoice exists;
 * such rows still appear on the subscriber ledger as a credit.
 *
 * Invoice status is derived from the sum of its completed payments:
 *   sum == 0            -> unpaid
 *   0 < sum < total     -> partial
 *   sum >= total        -> paid
 * (see App\Models\Invoice::refreshStatus()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('subscriber_id')->constrained('subscribers');
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $t->string('number')->unique();              // RCP-yymm-0001
            $t->decimal('amount', 12, 2);
            $t->string('method', 30)->default('cash');   // cash|upi|card|netbanking|cheque|wallet|adjustment
            $t->string('reference')->nullable();         // UTR / cheque no / gateway txn id
            $t->timestamp('paid_at');
            $t->string('status', 20)->default('completed'); // completed|pending|failed
            $t->text('notes')->nullable();

            $t->timestamps();
            $t->index(['tenant_id', 'subscriber_id']);
            $t->index(['tenant_id', 'paid_at']);
        });

        // Invoices gained a "partial" state; widen nothing (status is a string)
        // but record the running paid figure for cheap listing/reporting.
        Schema::table('invoices', function (Blueprint $t) {
            $t->decimal('paid_amount', 12, 2)->default(0)->after('total');
            $t->text('notes')->nullable();
        });

        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE payments ENABLE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation_payments ON payments USING (tenant_id = current_setting('app.current_tenant')::bigint)");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_payments ON payments');
        }

        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['paid_amount', 'notes']);
        });

        Schema::dropIfExists('payments');
    }
};
