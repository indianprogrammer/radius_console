<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the unused `is_default` flag from tax rates.
 *
 * Plans no longer need a default tax — taxes are chosen per plan via the
 * plan_tax_rate pivot. Dropping the column keeps the schema lean.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tax_rates', 'is_default')) {
            Schema::table('tax_rates', function ($t) {
                $t->dropColumn('is_default');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tax_rates', 'is_default')) {
            Schema::table('tax_rates', function ($t) {
                $t->boolean('is_default')->default(false);
            });
        }
    }
};
