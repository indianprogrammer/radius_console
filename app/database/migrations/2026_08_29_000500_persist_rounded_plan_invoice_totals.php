<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist rounded (ceiling) totals so the database is the source of truth.
 *
 *  - plans.total        : ceil(price + summed taxes). Stored as a round figure
 *                         so listing/querying doesn't recompute ad-hoc.
 *  - invoices.total      : ceil(subtotal + tax_amount). Added alongside the
 *                         existing subtotal/tax_amount snapshot columns.
 *
 * Ceiling rounding guarantees the charged amount never undercharges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->decimal('total', 12, 2)->nullable()->after('price');
        });

        Schema::table('invoices', function (Blueprint $t) {
            $t->decimal('total', 12, 2)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn('total');
        });

        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn('total');
        });
    }
};
