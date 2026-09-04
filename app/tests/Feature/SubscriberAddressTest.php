<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Src\Ports\RadiusClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Subscriber Billing / Installation address block.
 *
 * Focus is on the reconciliation SubscriberController::normaliseAddress() does,
 * because those are the rules the raw request cannot express on its own:
 * an absent switch, the billing mirror, and the all-or-nothing map pin.
 */
class SubscriberAddressTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Address ISP', 'domain' => 'address.test', 'slug' => 'address', 'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Home 50', 'price' => 599,
            'duration' => 1, 'duration_unit' => 'months',
        ]);

        // Never touch the live RADIUS server.
        $this->mock(RadiusClient::class, function (Mockery\MockInterface $m) {
            $m->shouldReceive('createUser')->andReturn(['id' => 999]);
            $m->shouldReceive('updateUser')->andReturn(['id' => 999]);
            $m->shouldReceive('deleteUser')->andReturn(['ok' => true]);
            $m->shouldReceive('getUser')->andReturn(['user' => ['id' => 999]]);
        });
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'     => 'Asha',
            'last_name'      => 'Rao',
            'access_type'    => 'pppoe',
            'pppoe_username' => 'asha.rao',
            'pppoe_password' => 'secret-pppoe',
            'plan_id'        => $this->plan->id,
            'billing_type'   => 1,
            'ip_mode'        => 2,

            'billing_address' => '12 Residency Road',
            'billing_city'    => 'Bengaluru',
            'billing_state'   => 'Karnataka',
            'billing_zip'     => '560025',
            'billing_country' => 'India',
        ], $overrides);
    }

    private function create(array $overrides = []): Subscriber
    {
        $this->post($this->url('/subscribers'), $this->payload($overrides))
            ->assertRedirect($this->url('/subscribers'));

        return Subscriber::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    }

    public function test_both_addresses_and_the_map_pin_are_stored(): void
    {
        $sub = $this->create([
            'installation_address'      => '50 Mahatma Gandhi Road',
            'installation_landmark'     => 'Opposite the metro station',
            'installation_place_label'  => '50, MG Road, Bengaluru, Karnataka 560001, India',
            'city'                      => 'Bengaluru',
            'state'                     => 'Karnataka',
            'zip'                       => '560001',
            'country'                   => 'India',
            'latitude'                  => '12.9755264',
            'longitude'                 => '77.6067902',
        ]);

        $this->assertSame('12 Residency Road', $sub->billing_address);
        $this->assertSame('560025', $sub->billing_zip);

        $this->assertSame('50 Mahatma Gandhi Road', $sub->installation_address);
        $this->assertSame('Opposite the metro station', $sub->installation_landmark);
        $this->assertSame('560001', $sub->zip);
        $this->assertSame('12.9755264', (string) $sub->latitude);
        $this->assertSame('77.6067902', (string) $sub->longitude);
        $this->assertFalse($sub->installation_same_as_billing);
    }

    /** The switch is absent from the POST when off, so it must be written false. */
    public function test_same_as_billing_defaults_to_false_when_the_switch_is_absent(): void
    {
        $sub = $this->create(['installation_address' => '50 MG Road']);

        $this->assertFalse($sub->installation_same_as_billing);
        $this->assertSame('50 MG Road', $sub->installation_address);
    }

    /**
     * With the flag on and the installation fields left blank, billing is
     * copied server-side — so the row is right even if the browser JS never ran.
     */
    public function test_same_as_billing_copies_billing_into_blank_installation_fields(): void
    {
        $sub = $this->create([
            'installation_same_as_billing' => 1,
            'installation_address'         => '',
            'city'                         => '',
            'state'                        => '',
            'zip'                          => '',
            'country'                      => '',
        ]);

        $this->assertTrue($sub->installation_same_as_billing);
        $this->assertSame('12 Residency Road', $sub->installation_address);
        $this->assertSame('Bengaluru', $sub->city);
        $this->assertSame('Karnataka', $sub->state);
        $this->assertSame('560025', $sub->zip);
        $this->assertSame('India', $sub->country);
    }

    /**
     * The installation fields stay editable even with the flag set: a value
     * that was actually typed must survive the mirror.
     */
    public function test_an_edited_installation_field_is_not_overwritten_by_the_mirror(): void
    {
        $sub = $this->create([
            'installation_same_as_billing' => 1,
            'installation_address'         => '50 Mahatma Gandhi Road',
            'city'                         => '',
        ]);

        $this->assertSame('50 Mahatma Gandhi Road', $sub->installation_address);
        // The blank one still inherits.
        $this->assertSame('Bengaluru', $sub->city);
    }

    /** Clearing the pin must null BOTH columns, never leave half a coordinate. */
    public function test_clearing_the_pin_nulls_both_coordinates(): void
    {
        $sub = $this->create([
            'latitude'  => '12.9755264',
            'longitude' => '77.6067902',
        ]);

        $this->put($this->url('/subscribers/' . $sub->id), $this->payload([
            'latitude'  => '',
            'longitude' => '',
        ]))->assertRedirect();

        $sub->refresh();
        $this->assertNull($sub->latitude);
        $this->assertNull($sub->longitude);
    }

    /** A lone coordinate is meaningless — the pair is required together. */
    public function test_a_half_submitted_coordinate_is_rejected(): void
    {
        $this->post($this->url('/subscribers'), $this->payload([
            'latitude' => '12.9755264',
            // longitude deliberately omitted
        ]))->assertSessionHasErrors('longitude');
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $this->post($this->url('/subscribers'), $this->payload([
            'latitude'  => '99.5',
            'longitude' => '200.1',
        ]))->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_the_address_survives_an_edit_round_trip(): void
    {
        $sub = $this->create([
            'installation_address' => '50 MG Road',
            'city'                 => 'Bengaluru',
            'latitude'             => '12.9755264',
            'longitude'            => '77.6067902',
        ]);

        $this->get($this->url('/subscribers/' . $sub->id . '/edit'))
            ->assertOk()
            ->assertSee('Billing Address')
            ->assertSee('Installation Address')
            ->assertSee('50 MG Road', false)
            ->assertSee('12.9755264', false);

        $this->put($this->url('/subscribers/' . $sub->id), $this->payload([
            'installation_address' => '99 Brigade Road',
            'city'                 => 'Bengaluru',
            'latitude'             => '12.9700000',
            'longitude'            => '77.6000000',
        ]))->assertRedirect();

        $sub->refresh();
        $this->assertSame('99 Brigade Road', $sub->installation_address);
        $this->assertSame('12.9700000', (string) $sub->latitude);
    }
}
