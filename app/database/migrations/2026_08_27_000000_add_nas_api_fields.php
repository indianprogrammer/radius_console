<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add RADIUS-managed API connection fields to the local NAS mirror.
 * These map 1:1 to the external RADIUS /api/nas schema (SRD §4.2) so the
 * local record is the source of truth and is pushed to RADIUS verbatim.
 *
 * - api_host / api_port / api_username / api_password : device API (MikroTik/CLI)
 * - api_enabled : master switch; api_* detail fields are hidden in the UI
 *   unless this is true (default false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nas', function (Blueprint $t) {
            $t->string('api_host')->nullable()->after('api_enabled');
            $t->string('api_port')->nullable()->after('api_host');
            $t->string('api_username')->nullable()->after('api_port');
            $t->string('api_password')->nullable()->after('api_username');
        });
    }

    public function down(): void
    {
        Schema::table('nas', function (Blueprint $t) {
            $t->dropColumn(['api_host', 'api_port', 'api_username', 'api_password']);
        });
    }
};
