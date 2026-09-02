<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales pipeline — "Sales" menu group (SRD §5.0 #7 "Leads").
 *
 * A lead is a PROSPECT, deliberately not a `subscribers` row: it has no RADIUS
 * account, no plan obligation and no billing identity, and most leads never
 * become subscribers. Modelling them as inactive subscribers would pollute
 * every subscriber count, RADIUS sync and invoice query in the app.
 *
 * The pipeline hands off to work that already exists rather than duplicating it:
 *   lead → quotation (`leads.quote_id`, created by LeadService) → invoice
 *   won lead → subscriber (`leads.subscriber_id`, linked after onboarding)
 *
 * `lead_activities` is an immutable trail, same reasoning as `ticket_events`:
 * "who called this prospect and when" is the substance of sales work, so it
 * cannot live in a column that the next edit overwrites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');

            $t->string('number', 40);              // LEAD-000001, unique per tenant
            $t->string('name', 150);
            $t->string('company', 150)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('alternate_phone', 20)->nullable();

            $t->text('address')->nullable();
            $t->string('city', 100)->nullable();
            $t->string('state', 100)->nullable();
            $t->string('pincode', 12)->nullable();

            // walk_in|phone|website|referral|campaign|social|partner|other
            $t->string('source', 30)->default('phone');
            // new|contacted|qualified|proposal|negotiation|won|lost
            $t->string('status', 30)->default('new');
            $t->string('rating', 20)->default('warm');     // hot|warm|cold

            // What they are interested in, and what it is worth if it closes.
            $t->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $t->decimal('estimated_value', 12, 2)->default(0);

            // Who owns the lead. `Staff::ROLES` already has a 'sales' role.
            $t->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->foreignId('franchise_id')->nullable()->constrained('franchises')->nullOnDelete();

            // Drives the Follow-ups queue.
            $t->timestamp('next_follow_up_at')->nullable();
            $t->timestamp('last_contacted_at')->nullable();

            $t->timestamp('won_at')->nullable();
            $t->timestamp('lost_at')->nullable();
            $t->string('lost_reason', 200)->nullable();

            // Hand-offs to the documents / accounts the lead produced.
            $t->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $t->foreignId('subscriber_id')->nullable()->constrained('subscribers')->nullOnDelete();

            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['tenant_id', 'number']);
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'assigned_staff_id']);
            $t->index(['tenant_id', 'next_follow_up_at']);
        });

        Schema::create('lead_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();

            // created|note|call|email|meeting|visit|status|assigned|follow_up|quoted|won|lost
            $t->string('type', 30);
            $t->foreignId('from_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->foreignId('to_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30)->nullable();
            $t->text('note')->nullable();
            $t->string('actor', 150)->nullable();    // who performed it
            $t->timestamp('occurred_at')->nullable();

            $t->timestamps();

            $t->index(['lead_id', 'created_at']);
        });

        if (config('database.default') === 'pgsql') {
            foreach (['leads', 'lead_activities'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation_{$table} ON {$table} USING (tenant_id = current_setting('app.current_tenant')::bigint)");
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            foreach (['lead_activities', 'leads'] as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_{$table} ON {$table}");
            }
        }

        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};