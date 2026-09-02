<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logs — the "Logs" menu group (SRD §5.0 #10, §9.8).
 *
 * The SRD already names `audit_log` as the data-model entity for this
 * (§8), so this migration WIDENS that table instead of adding a parallel
 * one: a second "activity" table would split "who did what" across two
 * places and immediately disagree with itself.
 *
 * One table, many channels. Audit Logs, Login History, Login Fail Attempts,
 * SMS / Email / Call / WhatsApp / Aadhaar Logs and User Syslogs are all the
 * same shape — actor, action, object, outcome, timestamp — differing only in
 * WHERE the event came from. `channel` is that discriminator, so every log
 * page is one query on one index and a new channel needs no migration.
 *
 * The existing `payload` text column becomes the structured context (cast to
 * array on the model), rather than adding a near-duplicate `context` column.
 * `ActivityLogger` redacts secrets before anything reaches it (SRD §9.4:
 * "no secrets in logs").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $t) {
            // Which log page the row belongs to. Defaulted so the rows written
            // before this migration (if any) stay readable as audit entries.
            $t->string('channel', 20)->default('audit')->after('tenant_id');

            // Actor NAME snapshot beside the existing `user_id` FK: staff leave,
            // users get deleted, and a log that then reads "user #7" is useless.
            $t->string('actor', 150)->nullable()->after('user_id');

            // What the action was performed ON. Kept as loose strings, not an FK:
            // the log outlives the row it describes, and a cascade would erase
            // exactly the history an audit exists to preserve.
            $t->string('object_type', 100)->nullable()->after('action');
            $t->string('object_id', 40)->nullable()->after('object_type');
            $t->string('object_label', 200)->nullable()->after('object_id');

            $t->string('status', 20)->default('success')->after('object_label'); // success|failed|pending
            $t->text('message')->nullable()->after('status');

            // Request provenance — the substance of a login-attempt log.
            $t->string('ip_address', 45)->nullable()->after('message');
            $t->string('user_agent', 255)->nullable()->after('ip_address');

            $t->index(['tenant_id', 'channel', 'created_at']);
            $t->index(['tenant_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'channel', 'created_at']);
            $t->dropIndex(['tenant_id', 'channel', 'status']);

            $t->dropColumn([
                'channel', 'actor',
                'object_type', 'object_id', 'object_label',
                'status', 'message', 'ip_address', 'user_agent',
            ]);
        });
    }
};
