<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax support for billing & invoices.
 *
 *  - plans.tax_rate  : per-plan tax percentage applied when invoicing a
 *                      subscriber on this plan (e.g. 18.00 for 18%).
 *  - invoices.tax_rate      : percentage snapshot at invoice time.
 *  - invoices.subtotal      : pre-tax amount.
 *  - invoices.tax_amount    : computed tax on the subtotal.
 *  - invoices.amount        : re-used as the GRAND TOTAL (subtotal + tax).
 *
 * Backwards compatible: existing invoices keep `amount` as the total; the
 * new subtotal/tax columns default to NULL and are only filled by the
 * invoice generator going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->unsignedBigInteger('tax_rate_id')->nullable()->after('tax_rate');
        });

        Schema::table('invoices', function (Blueprint $t) {
            $t->decimal('tax_rate', 5, 2)->nullable();
            $t->decimal('subtotal', 12, 2)->nullable();
            $t->decimal('tax_amount', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['tax_rate', 'subtotal', 'tax_amount']);
        });

        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn('tax_rate');
        });
    }
};
