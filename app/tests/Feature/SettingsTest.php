<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings: persistence of the schema-driven sections, the toggle edge case,
 * tenant isolation, and the two behaviours settings now drive (invoice prefix /
 * payment terms and the ticket SLA).
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Settings ISP', 'domain' => 'settings.test', 'slug' => 'settings', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    public function test_index_redirects_to_the_company_profile_section(): void
    {
        $this->get($this->url('/settings'))
            ->assertRedirect($this->url('/settings/profile'));
    }

    public function test_every_schema_section_renders(): void
    {
        foreach (array_keys(Setting::SCHEMA) as $section) {
            $this->get($this->url('/settings/' . $section))
                ->assertOk()
                ->assertSee(Setting::SCHEMA[$section]['label']);
        }
    }

    public function test_an_unknown_section_is_not_found(): void
    {
        $this->get($this->url('/settings/nope'))->assertNotFound();
        $this->put($this->url('/settings/nope'), [])->assertNotFound();
    }

    public function test_values_are_saved_and_read_back_through_the_model(): void
    {
        $this->put($this->url('/settings/billing'), [
            'settings' => [
                'billing__invoice_prefix'    => 'BILL',
                'billing__invoice_due_days'  => '30',
                'billing__grace_period_days' => '5',
                // Toggles posted as unchecked (absent) below.
            ],
        ])->assertRedirect($this->url('/settings/billing'));

        $this->assertSame('BILL', Setting::get('billing.invoice_prefix', $this->tenant->id));
        $this->assertSame(30, Setting::int('billing.invoice_due_days', $this->tenant->id));
    }

    public function test_an_unchecked_toggle_is_stored_as_off_instead_of_falling_back_to_its_default(): void
    {
        // The schema default for this key is ON, so a naive "skip absent keys"
        // save would leave it looking enabled after the user switched it off.
        $this->assertTrue(Setting::bool('billing.round_invoice_total', $this->tenant->id));

        $this->put($this->url('/settings/billing'), [
            'settings' => ['billing__invoice_prefix' => 'INV'],
        ])->assertRedirect();

        Setting::forget($this->tenant->id);
        $this->assertFalse(Setting::bool('billing.round_invoice_total', $this->tenant->id));
    }

    public function test_invalid_values_are_rejected_with_the_field_label(): void
    {
        $this->put($this->url('/settings/billing'), [
            'settings' => ['billing__invoice_due_days' => '9999'],
        ])->assertSessionHasErrors('settings.billing__invoice_due_days');

        $this->assertSame(15, Setting::int('billing.invoice_due_days', $this->tenant->id));
    }

    public function test_company_profile_writes_to_the_tenant_row_but_cannot_change_the_domain(): void
    {
        $this->put($this->url('/settings/profile'), [
            'name'          => 'Renamed ISP',
            'theme_default' => 'dark',
            'logo_url'      => 'https://cdn.example.com/logo.png',
            'domain'        => 'hijacked.test',
            'slug'          => 'hijacked',
        ])->assertRedirect($this->url('/settings/profile'));

        $this->tenant->refresh();
        $this->assertSame('Renamed ISP', $this->tenant->name);
        $this->assertSame('dark', $this->tenant->theme_default);
        // Host and slug identify the tenant / namespace RADIUS usernames.
        $this->assertSame('settings.test', $this->tenant->domain);
        $this->assertSame('settings', $this->tenant->slug);
    }

    public function test_settings_do_not_leak_between_tenants(): void
    {
        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-settings.test', 'slug' => 'othersettings', 'status' => 'active',
        ]);

        Setting::put('billing.invoice_prefix', 'AAA', $this->tenant->id);
        Setting::put('billing.invoice_prefix', 'BBB', $other->id);

        $this->assertSame('AAA', Setting::get('billing.invoice_prefix', $this->tenant->id));
        $this->assertSame('BBB', Setting::get('billing.invoice_prefix', $other->id));
    }

    public function test_unset_keys_fall_back_to_the_schema_default(): void
    {
        $this->assertSame('INV', Setting::get('billing.invoice_prefix', $this->tenant->id));
        $this->assertSame(15, Setting::int('billing.invoice_due_days', $this->tenant->id));
        $this->assertSame('Asia/Kolkata', Setting::get('localization.timezone', $this->tenant->id));
    }

    public function test_the_ticket_sla_setting_drives_the_due_date(): void
    {
        Setting::put('tickets.sla_high_hours', '2', $this->tenant->id);

        $due = Ticket::slaDueAt('high', $this->tenant->id);

        // 2h, not the 24h SLA_HOURS fallback.
        $this->assertLessThan(now()->addHours(3), $due);
        $this->assertGreaterThan(now()->addHour(), $due);
    }

    public function test_the_radius_api_section_is_standalone_and_omits_the_tab_strip(): void
    {
        $this->get($this->url('/settings/radius'))
            ->assertOk()
            ->assertSee('RADIUS Server URL')
            // Reached from Radius Control, so it must not render the tab strip.
            ->assertDontSee('settings-tabs')
            ->assertViewHas('sections', fn ($sections) => !array_key_exists('radius', $sections));
    }

    public function test_the_radius_url_falls_back_to_config_until_it_is_saved(): void
    {
        config(['radius.base_url' => 'http://10.0.0.9:8001/api']);

        $this->assertSame('http://10.0.0.9:8001/api', Setting::get('radius.api_base_url', $this->tenant->id));
        $this->assertSame('http://10.0.0.9:8001/api', Setting::radiusBaseUrl($this->tenant->id));
    }

    public function test_the_saved_radius_url_wins_and_loses_its_trailing_slash(): void
    {
        config(['radius.base_url' => 'http://10.0.0.9:8001/api']);

        $this->put($this->url('/settings/radius'), [
            'settings' => ['radius__api_base_url' => 'http://192.168.1.50:8001/api/'],
        ])->assertRedirect($this->url('/settings/radius'));

        // Callers concatenate as `$base . '/auth/login'`, so the slash must go.
        $this->assertSame('http://192.168.1.50:8001/api', Setting::radiusBaseUrl($this->tenant->id));
    }

    public function test_a_malformed_radius_url_is_rejected(): void
    {
        $this->put($this->url('/settings/radius'), [
            'settings' => ['radius__api_base_url' => 'not a url'],
        ])->assertSessionHasErrors('settings.radius__api_base_url');

        $this->assertSame('', Setting::where('tenant_id', $this->tenant->id)
            ->where('key', 'radius.api_base_url')->value('value') ?? '');
    }
}
