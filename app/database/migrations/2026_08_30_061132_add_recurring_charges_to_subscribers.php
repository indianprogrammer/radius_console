<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add recurring_charges JSON column to store dynamic recurring billing
 * rows (e.g. IP billing, OLT charges, router rental, etc.).
 *
 * Structure per row:
 *   label     string  – description of the recurring charge
 *   amount    float   – default / fixed amount
 *   qty       int     – quantity / multiplier (default 1)
 *   taxable   bool    – whether this line attracts GST
 *   billing_cycle string – monthly | quarterly | yearly | one-time
 *   status    string  – active | inactive
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->json('recurring_charges')->nullable()->after('special_charges');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn('recurring_charges');
        });
    }
};
