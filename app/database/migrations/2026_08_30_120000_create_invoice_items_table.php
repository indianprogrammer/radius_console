<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-invoice line items. Each row captures a single billable element
 * attached to an invoice at the time of invoicing, so historical invoices
 * remain correct even if products/plans/taxes change later.
 *
 * Source mirrors the subscriber's billing_items classification:
 *   - refundable : security / deposit. Recorded as a positive line with
 *                  `is_refundable=1` so the UI / refund flow can surface it.
 *   - one-time   : installation / product charges. Invoiced once on the
 *                  current bill.
 *   - recurring  : monthly / quarterly / yearly charges. Invoiced per cycle
 *                  using `billing_cycle` and `next_bill_at`.
 *
 * Quantities, taxes, and totals are snapshotted onto the row.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('subscriber_id')->constrained('subscribers');

            // Classification (matches the subscriber's billing_items.type)
            $t->enum('type', ['refundable', 'one-time', 'recurring']);

            // Description & money
            $t->string('label', 200);
            $t->text('description')->nullable();
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('unit_price', 12, 2);
            $t->decimal('amount', 12, 2); // = qty * unit_price
            $t->boolean('taxable')->default(true);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2); // amount + tax_amount (rounded)

            // Refundable flag (true for security deposits)
            $t->boolean('is_refundable')->default(false);

            // Recurring scheduling
            $t->string('billing_cycle', 30)->nullable();   // monthly|quarterly|yearly
            $t->timestamp('next_bill_at')->nullable();     // next invoice due
            $t->string('status', 20)->default('active');   // active|inactive

            $t->timestamps();
            $t->index(['subscriber_id', 'type']);
            $t->index(['next_bill_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
