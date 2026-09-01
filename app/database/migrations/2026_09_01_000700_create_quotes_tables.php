<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations and Proforma Invoices — Billing & Invoices (SRD §5.0 #9
 * "Proforma Invoices").
 *
 * ONE table with a `type` discriminator rather than two near-identical tables:
 * both documents carry the same line items, totals and customer block, and
 * differ only in numbering series, wording and lifecycle vocabulary
 * (a quotation is accepted/declined, a proforma is issued then paid).
 * Splitting them would duplicate the item table, the totals maths and the
 * conversion path for no behavioural gain.
 *
 * Neither document is a receivable: they are explicitly NOT part of the ledger
 * or the collection figures until `convert()` produces a real invoice, at which
 * point `converted_invoice_id` links the two and the document is frozen.
 *
 * The customer can be an existing subscriber (`subscriber_id`) OR a free-text
 * prospect (`customer_name` etc.), because quoting someone who is not yet a
 * subscriber is the main use case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');

            $t->string('type', 20);                 // quotation | proforma
            $t->string('number', 40);               // unique per tenant+type
            $t->string('status', 20)->default('draft');

            // Either an existing subscriber…
            $t->foreignId('subscriber_id')->nullable()->constrained('subscribers')->nullOnDelete();
            // …or a prospect who is not on the books yet.
            $t->string('customer_name', 150)->nullable();
            $t->string('customer_email', 150)->nullable();
            $t->string('customer_phone', 20)->nullable();
            $t->text('customer_address')->nullable();
            $t->string('customer_gstin', 20)->nullable();

            $t->date('issue_date')->nullable();
            $t->date('valid_until')->nullable();    // quotation expiry

            // Totals snapshotted from the items, same shape as `invoices`.
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount_amount', 12, 2)->default(0);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('amount', 12, 2)->default(0);   // subtotal - discount + tax
            $t->decimal('total', 12, 2)->default(0);    // rounded payable

            $t->text('notes')->nullable();
            $t->text('terms')->nullable();

            // Set once the document becomes a real invoice; freezes the record.
            $t->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->timestamp('converted_at')->nullable();

            $t->timestamps();

            $t->unique(['tenant_id', 'type', 'number']);
            $t->index(['tenant_id', 'type', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();

            // Optional catalogue link; the label/price are still snapshotted so
            // editing a product later cannot rewrite an issued document.
            $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $t->string('label', 200);
            $t->text('description')->nullable();
            $t->unsignedInteger('qty')->default(1);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('amount', 12, 2)->default(0);       // qty * unit_price
            $t->boolean('taxable')->default(true);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2)->default(0);   // amount + tax_amount
            $t->unsignedInteger('sort_order')->default(0);

            $t->timestamps();

            $t->index(['quote_id', 'sort_order']);
        });

        if (config('database.default') === 'pgsql') {
            foreach (['quotes', 'quote_items'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation_{$table} ON {$table} USING (tenant_id = current_setting('app.current_tenant')::bigint)");
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_quote_items ON quote_items');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_quotes ON quotes');
        }

        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
