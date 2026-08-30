<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the fixed billing `cycle` enum-ish column on `plans` with a free
 * numeric `duration` plus a `duration_unit` ("days" | "months").
 *
 * Existing rows are migrated: monthly => 1 month, quarterly => 3 months,
 * yearly => 12 months. Anything unrecognised falls back to 1 month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedInteger('duration')->default(1)->after('price');
            $t->string('duration_unit')->default('months')->after('duration'); // days|months
        });

        if (Schema::hasColumn('plans', 'cycle')) {
            foreach (['monthly' => 1, 'quarterly' => 3, 'yearly' => 12] as $cycle => $months) {
                DB::table('plans')->where('cycle', $cycle)->update([
                    'duration' => $months,
                    'duration_unit' => 'months',
                ]);
            }

            Schema::table('plans', function (Blueprint $t) {
                $t->dropColumn('cycle');
            });
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->string('cycle')->default('monthly')->after('price');
        });

        DB::table('plans')->where('duration_unit', 'months')->where('duration', 3)->update(['cycle' => 'quarterly']);
        DB::table('plans')->where('duration_unit', 'months')->where('duration', 12)->update(['cycle' => 'yearly']);

        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn(['duration', 'duration_unit']);
        });
    }
};
