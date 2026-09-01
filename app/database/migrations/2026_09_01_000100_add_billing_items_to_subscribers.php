<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the missing `billing_items` JSON column on subscribers.
 *
 * `SubscriberController` (store + update) already validates and writes this
 * key and `App\Services\InvoiceService::generateFromSubscriber()` reads it to
 * build invoice line items, but the column was never migrated — so every
 * generated invoice came out with no line items (and a subscriber save that
 * included billing rows failed with "column does not exist").
 *
 * Structure per row (mirrors invoice_items.type):
 *   label          string  – description shown on the invoice line
 *   description    string  – optional longer text
 *   type           string  – refundable | one-time | recurring
 *   amount         float   – unit price
 *   qty            int     – quantity (default 1)
 *   taxable        bool    – whether the line attracts tax
 *   billing_cycle  string  – monthly | quarterly | yearly (recurring only)
 *   is_refundable  bool    – true for security deposits
 *   product_id     int     – optional link to products.id
 *   status         string  – active | inactive
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->json('billing_items')->nullable()->after('recurring_charges');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn('billing_items');
        });
    }
};
