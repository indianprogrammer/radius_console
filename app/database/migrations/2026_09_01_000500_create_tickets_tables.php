<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets / Helpdesk — "Support Tickets" menu group.
 *
 * Assignment is modelled on TWO levels, because "assign to one person",
 * "assign to several people" and "assign to a team" are different intents:
 *
 *  - `tickets.assigned_staff_id` is the single OWNER (the one accountable).
 *  - `ticket_assignees` is the full set of collaborators (many-to-many),
 *    always including the owner so "who is on this ticket?" is one query.
 *  - `tickets.staff_group_id` targets a whole team; on assignment the group's
 *    current members are expanded into `ticket_assignees` so later membership
 *    changes cannot silently rewrite history.
 *
 * Reassignment is never a bare UPDATE: `ticket_events` records an immutable
 * trail (`from_staff_id` -> `to_staff_id`) so an escalation can be audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');

            $t->string('number', 40);              // TKT-000001, unique per tenant
            $t->string('subject', 200);
            $t->text('description')->nullable();

            // installation|fault|billing|complaint|feedback|relocation|other
            $t->string('category', 30)->default('fault');
            $t->string('priority', 20)->default('medium');  // low|medium|high|urgent
            // open|assigned|in_progress|on_hold|resolved|closed|cancelled
            $t->string('status', 20)->default('open');
            $t->string('source', 20)->default('phone');     // phone|email|walk_in|app|web|whatsapp

            // Who it is about (all optional — a ticket may be internal).
            $t->foreignId('subscriber_id')->nullable()->constrained('subscribers')->nullOnDelete();
            $t->foreignId('franchise_id')->nullable()->constrained('franchises')->nullOnDelete();

            // ---- Assignment ----
            $t->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->foreignId('staff_group_id')->nullable()->constrained('staff_groups')->nullOnDelete();
            $t->timestamp('assigned_at')->nullable();

            $t->foreignId('created_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $t->timestamp('due_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->text('resolution')->nullable();

            $t->string('contact_name', 150)->nullable();
            $t->string('contact_phone', 20)->nullable();
            $t->text('address')->nullable();

            $t->timestamps();

            $t->unique(['tenant_id', 'number']);
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'assigned_staff_id']);
        });

        // Collaborators. `is_primary` marks the row mirroring
        // `tickets.assigned_staff_id` so the owner survives a group expansion.
        Schema::create('ticket_assignees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $t->boolean('is_primary')->default(false);
            $t->timestamp('assigned_at')->nullable();
            $t->timestamps();

            $t->unique(['ticket_id', 'staff_id']);
        });

        // Immutable activity trail: assignment, reassignment, status and notes.
        Schema::create('ticket_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();

            // created|assigned|reassigned|unassigned|status|comment|resolved|reopened
            $t->string('type', 30);
            $t->foreignId('from_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->foreignId('to_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20)->nullable();
            $t->text('note')->nullable();
            $t->string('actor', 150)->nullable();     // who performed it (login name)

            $t->timestamps();

            $t->index(['ticket_id', 'created_at']);
        });

        if (config('database.default') === 'pgsql') {
            foreach (['tickets', 'ticket_assignees', 'ticket_events'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation_{$table} ON {$table} USING (tenant_id = current_setting('app.current_tenant')::bigint)");
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            foreach (['ticket_events', 'ticket_assignees', 'tickets'] as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_{$table} ON {$table}");
            }
        }

        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_assignees');
        Schema::dropIfExists('tickets');
    }
};
