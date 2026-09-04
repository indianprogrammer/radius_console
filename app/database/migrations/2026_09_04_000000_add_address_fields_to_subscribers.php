<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split the subscriber CAF address into a BILLING address and an
 * INSTALLATION address (the latter carrying a map-plotted geo pin).
 *
 * The 2026_08_29_000800 CAF migration already gave us:
 *   billing_address       text     – billing street address
 *   installation_address  text     – installation street address
 *   country/state/city/zip         – ONE generic set, reused here as the
 *                                    INSTALLATION locality (the map pin and
 *                                    the field engineer both need this one)
 *   latitude/longitude    decimal  – installation geo pin
 *
 * What was missing was the billing-side locality (a customer is often billed
 * at a head office while the line is installed elsewhere) and the small amount
 * of state the map picker needs. Added here:
 *
 *   billing_city / billing_state / billing_zip / billing_country
 *                                  – billing locality, independent of install
 *   installation_same_as_billing   – form convenience flag; when true the
 *                                    installation fields mirror billing
 *   installation_landmark          – free-text "opposite the water tank"
 *   installation_place_label       – the geocoder's resolved display name for
 *                                    the pin, kept so the saved coordinates
 *                                    can be shown as a human-readable place
 *                                    without re-querying the geocoder
 *
 * All columns are nullable / defaulted so existing rows keep working.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            // Billing locality (billing_address itself already exists).
            $t->string('billing_city', 100)->nullable()->after('billing_address');
            $t->string('billing_state', 100)->nullable()->after('billing_city');
            $t->string('billing_zip', 12)->nullable()->after('billing_state');
            $t->string('billing_country', 100)->nullable()->after('billing_zip');

            // Installation extras. The locality (city/state/zip/country) and
            // the pin (latitude/longitude) already exist from the CAF migration.
            $t->boolean('installation_same_as_billing')->default(false)->after('installation_address');
            $t->string('installation_landmark', 200)->nullable()->after('installation_same_as_billing');
            $t->string('installation_place_label', 255)->nullable()->after('installation_landmark');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn([
                'billing_city',
                'billing_state',
                'billing_zip',
                'billing_country',
                'installation_same_as_billing',
                'installation_landmark',
                'installation_place_label',
            ]);
        });
    }
};
