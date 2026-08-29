<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reshape `plans` into a financial-only table and wire subscribers to the new
 * RADIUS-synced `bandwidth_profiles` table.
 *
 *  - plans: drop bandwidth/radius columns, add bandwidth_profile_id (FK).
 *  - subscribers: keep plan_id (billing), add bandwidth_profile_id (network).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn([
                'download_mbps', 'upload_mbps', 'data_limit_gb', 'duration_days',
                'fup_threshold_gb', 'fup_download_mbps', 'fup_upload_mbps',
                'simultaneous_use', 'radius_profile_id',
            ]);
            $t->foreignId('bandwidth_profile_id')->nullable()->constrained('bandwidth_profiles');
        });

        Schema::table('subscribers', function (Blueprint $t) {
            $t->foreignId('bandwidth_profile_id')->nullable()->after('plan_id')->constrained('bandwidth_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropForeign(['bandwidth_profile_id']);
            $t->dropColumn('bandwidth_profile_id');
        });

        Schema::table('plans', function (Blueprint $t) {
            $t->dropForeign(['bandwidth_profile_id']);
            $t->dropColumn('bandwidth_profile_id');
            $t->integer('download_mbps');
            $t->integer('upload_mbps');
            $t->integer('data_limit_gb')->nullable();
            $t->integer('duration_days');
            $t->integer('fup_threshold_gb')->nullable();
            $t->integer('fup_download_mbps')->nullable();
            $t->integer('fup_upload_mbps')->nullable();
            $t->integer('simultaneous_use')->default(1);
            $t->string('radius_profile_id')->nullable();
        });
    }
};
