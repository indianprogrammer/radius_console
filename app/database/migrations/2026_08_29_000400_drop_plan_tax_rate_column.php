<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the now-unused per-plan flat `tax_rate` column.
 *
 * Plans are taxed exclusively through the `plan_tax_rate` pivot (multiple taxes
 * or none), so the standalone percentage field is redundant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'tax_rate')) {
            Schema::table('plans', function ($t) {
                $t->dropColumn('tax_rate');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('plans', 'tax_rate')) {
            Schema::table('plans', function ($t) {
                $t->decimal('tax_rate', 5, 2)->default(0);
            });
        }
    }
};
