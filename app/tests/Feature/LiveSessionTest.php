<?php

namespace Tests\Feature;

use App\Models\Nas;
use App\Models\Plan;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Services\LiveSessionCollector;
use App\Src\Ports\RadiusClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Live Sessions (Logs > Live Sessions).
 *
 * The page is a read-through proxy over the RADIUS server, so what is worth
 * testing is not storage but the decisions the collector makes on data it does
 * not control: tenant isolation on a single-tenant upstream, the merge of open
 * accounting rows, health derivation, and degrading gracefully when the server
 * is unreachable.
 */
class LiveSessionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Live ISP', 'domain' => 'live.test', 'slug' => 'live', 'status' => 'active',
        ]);
        $this->other = Tenant::create([
            'name' => 'Rival ISP', 'domain' => 'rival.test', 'slug' => 'rival', 'status' => 'active',
        ]);
    }

    private function url(string $path = '/logs/live-sessions', ?Tenant $tenant = null): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . ($tenant ?? $this->tenant)->domain . '/' . ltrim($path, '/');
    }

    /**
     * Stub the RADIUS port. Both endpoints are stubbed on every call so a test
     * that only cares about one still gets a well-formed answer from the other.
     */
    private function radius(array $sessions = [], array $accounting = []): void
    {
        $this->mock(RadiusClient::class, function (Mockery\MockInterface $m) use ($sessions, $accounting) {
            $m->shouldReceive('listSessions')
                ->andReturn(['count' => count($sessions), 'sessions' => $sessions]);
            $m->shouldReceive('listAccounting')
                ->andReturn(['count' => count($accounting), 'records' => $accounting]);
        });
    }

    /**
     * One `active_sessions` row as the RADIUS core returns it.
     *
     * Not named `session()` — that is a public helper on Laravel's TestCase and
     * overriding it with a private method is a fatal error at load time.
     */
    private function sessionRow(array $overrides = []): array
    {
        return array_merge([
            'session_id'    => '81000001',
            'username'      => 'live_asha',
            'user_id'       => 11,
            'nas_ip'        => '10.99.0.5',
            'framed_ip'     => '100.64.0.10',
            'framed_ipv6'   => null,
            'mac_address'   => '08:00:27:CA:F7:2E',
            'start_time'    => now()->subHours(2)->subMinutes(15)->toIso8601String(),
            'last_update'   => now()->subMinute()->toIso8601String(),
            'input_octets'  => 1048576,
            'output_octets' => 5242880,
        ], $overrides);
    }

    private function subscriber(string $username, ?Tenant $tenant = null, array $overrides = []): Subscriber
    {
        $tenant ??= $this->tenant;

        return Subscriber::create(array_merge([
            'tenant_id'       => $tenant->id,
            'username'        => $username,
            'radius_username' => $tenant->slug . '_' . $username,
            'password_enc'    => 'enc:secret',
            'status'          => 'ACTIVE',
        ], $overrides));
    }

    // ---- Collection -------------------------------------------------------

    public function test_a_live_session_is_listed_with_its_network_detail(): void
    {
        $this->subscriber('asha', null, ['first_name' => 'Asha', 'last_name' => 'Rao']);
        Nas::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Central POP',
            'nas_ip' => '10.99.0.5', 'shared_secret' => 'supersecret123',
        ]);

        $this->radius([$this->sessionRow()]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Live Sessions')
            ->assertSee('asha')
            ->assertSee('Asha Rao')
            ->assertSee('Central POP')      // NAS resolved from the local registry
            ->assertSee('100.64.0.10')
            ->assertSee('08:00:27:CA:F7:2E')
            ->assertSee('2h 15m')           // uptime derived from start_time
            ->assertSee('Online');
    }

    public function test_the_plan_and_a_link_to_the_subscriber_are_shown(): void
    {
        $plan = Plan::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Home 100',
            'price' => 799, 'duration' => 1, 'duration_unit' => 'months',
        ]);
        $sub = $this->subscriber('asha', null, ['plan_id' => $plan->id]);

        $this->radius([$this->sessionRow()]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Home 100')
            ->assertSee('/subscribers/' . $sub->id . '/edit');
    }

    public function test_a_session_for_another_company_is_never_shown(): void
    {
        $this->subscriber('asha');
        $this->subscriber('vikram', $this->other);

        $this->radius([
            $this->sessionRow(),
            // Same RADIUS server, different tenant namespace — the core is
            // single-tenant, so isolation is entirely this platform's job.
            $this->sessionRow(['session_id' => '81000002', 'username' => 'rival_vikram']),
        ]);

        $response = $this->get($this->url())->assertOk();

        $response->assertSee('asha')->assertDontSee('vikram');
        // And it is reported rather than silently dropped.
        $response->assertSee('belonging to other companies', false);
        $response->assertViewHas('foreign', 1);
    }

    public function test_a_username_mapped_by_a_previous_slug_is_still_ours(): void
    {
        // Provisioned when the company's slug was different: the stored
        // radius_username no longer matches the prefix rule, but the mapping is
        // authoritative.
        $this->subscriber('asha', null, ['radius_username' => 'oldslug_asha']);

        $this->radius([$this->sessionRow(['username' => 'oldslug_asha'])]);

        $this->get($this->url())->assertOk()->assertSee('asha')->assertViewHas('foreign', 0);
    }

    public function test_an_open_accounting_record_recovers_a_session_missing_from_the_live_list(): void
    {
        $this->subscriber('asha');

        $this->radius([], [
            // No stop_time ⇒ still up, even though the core dropped it from
            // active_sessions (restart, or the stale sweep).
            [
                'session_id' => '810000fa', 'username' => 'live_asha',
                'nas_ip' => '10.99.0.5', 'framed_ip' => '100.64.0.22',
                'start_time' => now()->subMinutes(30)->toIso8601String(),
                'update_time' => now()->subMinutes(2)->toIso8601String(),
                'stop_time' => null, 'session_time' => 1800,
                'input_octets' => 2048, 'output_octets' => 4096,
            ],
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('100.64.0.22')
            ->assertSee('unconfirmed')
            ->assertViewHas('totals', fn (array $t) => $t['total'] === 1 && $t['recovered'] === 1);
    }

    public function test_a_closed_accounting_record_is_not_a_live_session(): void
    {
        $this->subscriber('asha');

        $this->radius([], [[
            'session_id' => '810000fb', 'username' => 'live_asha',
            'start_time' => now()->subHours(3)->toIso8601String(),
            'stop_time' => now()->subHour()->toIso8601String(),
            'session_time' => 7200, 'input_octets' => 10, 'output_octets' => 20,
        ]]);

        $this->get($this->url())
            ->assertOk()
            ->assertViewHas('totals', fn (array $t) => $t['total'] === 0);
    }

    public function test_the_live_list_wins_over_an_accounting_row_for_the_same_session(): void
    {
        $this->subscriber('asha');

        $this->radius(
            [$this->sessionRow(['framed_ip' => '100.64.0.10'])],
            [[
                'session_id' => '81000001', 'username' => 'live_asha',
                'framed_ip' => '10.0.0.99', 'stop_time' => null,
                'start_time' => now()->subHours(2)->toIso8601String(),
                'input_octets' => 1, 'output_octets' => 1,
            ]],
        );

        $this->get($this->url())
            ->assertOk()
            ->assertSee('100.64.0.10')
            ->assertDontSee('10.0.0.99')
            ->assertViewHas('totals', fn (array $t) => $t['total'] === 1);
    }

    // ---- Health -----------------------------------------------------------

    public function test_health_follows_the_freshness_of_the_accounting_stream(): void
    {
        $this->subscriber('asha');
        $this->subscriber('bala');
        $this->subscriber('chitra');

        $this->radius([
            $this->sessionRow(['session_id' => 's1', 'username' => 'live_asha',
                'last_update' => now()->subMinute()->toIso8601String()]),
            $this->sessionRow(['session_id' => 's2', 'username' => 'live_bala',
                'last_update' => now()->subMinutes(10)->toIso8601String()]),
            $this->sessionRow(['session_id' => 's3', 'username' => 'live_chitra',
                'last_update' => now()->subMinutes(45)->toIso8601String()]),
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertViewHas('totals', fn (array $t) => $t['total'] === 3
                && $t['online'] === 1     // 1 minute ago
                && $t['stale'] === 2);    // 10 minutes (idle) + 45 minutes (stale)
    }

    // ---- Filters ----------------------------------------------------------

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $this->subscriber('asha');
        $this->subscriber('bala');

        $this->radius([
            $this->sessionRow(['session_id' => 's1', 'username' => 'live_asha',
                'nas_ip' => '10.99.0.5', 'framed_ip' => '100.64.0.10']),
            $this->sessionRow(['session_id' => 's2', 'username' => 'live_bala',
                'nas_ip' => '10.99.0.9', 'framed_ip' => '100.64.0.20',
                'last_update' => now()->subHour()->toIso8601String()]),
        ]);

        // Free text over subscriber / IP / MAC / NAS / session id.
        $this->get($this->url('/logs/live-sessions?q=bala'))
            ->assertOk()->assertSee('bala')->assertDontSee('>asha<', false);

        $this->get($this->url('/logs/live-sessions?q=100.64.0.10'))
            ->assertOk()->assertViewHas('matched', 1);

        // NAS facet.
        $this->get($this->url('/logs/live-sessions?nas=10.99.0.9'))
            ->assertOk()->assertViewHas('matched', 1)->assertSee('bala');

        // Health facet: "stale" covers idle as well, which is what an operator
        // looking for trouble means by it.
        $this->get($this->url('/logs/live-sessions?health=stale'))
            ->assertOk()->assertViewHas('matched', 1)->assertSee('bala');

        $this->get($this->url('/logs/live-sessions?health=online'))
            ->assertOk()->assertViewHas('matched', 1)->assertSee('asha');
    }

    public function test_the_cards_summarise_every_session_while_a_filter_narrows_the_table(): void
    {
        $this->subscriber('asha');
        $this->subscriber('bala');

        $this->radius([
            $this->sessionRow(['session_id' => 's1', 'username' => 'live_asha']),
            $this->sessionRow(['session_id' => 's2', 'username' => 'live_bala']),
        ]);

        $this->get($this->url('/logs/live-sessions?q=asha'))
            ->assertOk()
            ->assertViewHas('matched', 1)
            // Cards must not follow the filter, or the numbers move as you type.
            ->assertViewHas('totals', fn (array $t) => $t['total'] === 2);
    }

    public function test_sorting_by_traffic_puts_the_heaviest_session_first(): void
    {
        $this->subscriber('asha');
        $this->subscriber('bala');

        $this->radius([
            $this->sessionRow(['session_id' => 's1', 'username' => 'live_asha',
                'input_octets' => 10, 'output_octets' => 10]),
            $this->sessionRow(['session_id' => 's2', 'username' => 'live_bala',
                'input_octets' => 1_000_000_000, 'output_octets' => 1_000_000_000]),
        ]);

        $this->get($this->url('/logs/live-sessions?sort=volume'))
            ->assertOk()
            ->assertViewHas('sessions', fn ($paginator) => $paginator->first()['username'] === 'live_bala');
    }

    public function test_paging_keeps_the_active_filters(): void
    {
        // The paginator is built by hand (the rows come from an API, not a
        // query), so the query string has to be handed to it explicitly or
        // page 2 silently drops the filter.
        for ($i = 1; $i <= 4; $i++) {
            $this->subscriber('user' . $i);
        }

        $this->radius(array_map(fn (int $i) => $this->sessionRow([
            'session_id' => 's' . $i, 'username' => 'live_user' . $i,
        ]), [1, 2, 3, 4]));

        $this->get($this->url('/logs/live-sessions?health=online&per_page=2'))
            ->assertOk()
            ->assertViewHas('sessions', fn ($paginator) => $paginator->total() === 4
                && $paginator->count() === 2
                && str_contains($paginator->nextPageUrl(), 'health=online')
                && str_contains($paginator->nextPageUrl(), 'page=2'));
    }

    // ---- Failure modes ----------------------------------------------------

    public function test_an_unreachable_radius_server_shows_a_banner_instead_of_failing(): void
    {
        $this->mock(RadiusClient::class, function (Mockery\MockInterface $m) {
            $m->shouldReceive('listSessions')->andThrow(new \RuntimeException('RADIUS GET /sessions failed: 500'));
            $m->shouldReceive('listAccounting')->andThrow(new \RuntimeException('RADIUS GET /accounting failed: 500'));
        });

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Could not read sessions from the RADIUS server')
            ->assertViewHas('error');
    }

    public function test_accounting_failing_alone_still_renders_the_live_list(): void
    {
        $this->subscriber('asha');

        $this->mock(RadiusClient::class, function (Mockery\MockInterface $m) {
            $m->shouldReceive('listSessions')
                ->andReturn(['count' => 1, 'sessions' => [$this->sessionRow()]]);
            $m->shouldReceive('listAccounting')->andThrow(new \RuntimeException('boom'));
        });

        $this->get($this->url())
            ->assertOk()
            ->assertSee('asha')
            // The recovery pass is best-effort; its failure is not worth a
            // banner while the authoritative list came through.
            ->assertViewHas('error', null);
    }

    public function test_an_empty_server_reads_as_nobody_online(): void
    {
        $this->radius();

        $this->get($this->url())
            ->assertOk()
            ->assertSee('Nobody is online at the moment')
            ->assertViewHas('totals', fn (array $t) => $t['total'] === 0);
    }

    public function test_sessions_that_all_belong_elsewhere_say_so_instead_of_nobody_online(): void
    {
        // Upstream has traffic, none of it ours. Reporting "nobody is online"
        // would read as a broken feed rather than correct isolation.
        $this->radius([$this->sessionRow(['username' => 'rival_vikram'])]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('none')
            ->assertDontSee('Nobody is online at the moment')
            ->assertViewHas('foreign', 1);
    }

    public function test_a_malformed_timestamp_does_not_break_the_page(): void
    {
        $this->subscriber('asha');

        $this->radius([$this->sessionRow(['start_time' => 'not-a-date', 'last_update' => ''])]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('asha')
            ->assertSee('No Data');   // health is unknown without a last_update
    }

    public function test_the_page_is_read_only(): void
    {
        $this->radius();

        // Session control (PoD / CoA) is deliberately not exposed here: this is
        // a log view. Kicking a subscriber belongs with subscriber management.
        $this->post($this->url(), [])->assertMethodNotAllowed();
        $this->put($this->url(), [])->assertMethodNotAllowed();
        $this->delete($this->url())->assertMethodNotAllowed();
    }

    // ---- Formatting -------------------------------------------------------

    public function test_uptime_is_formatted_to_two_units(): void
    {
        $this->assertSame('—', LiveSessionCollector::durationLabel(null));
        $this->assertSame('48s', LiveSessionCollector::durationLabel(48));
        $this->assertSame('15m', LiveSessionCollector::durationLabel(15 * 60));
        $this->assertSame('2h 15m', LiveSessionCollector::durationLabel(2 * 3600 + 15 * 60));
        $this->assertSame('3d 4h', LiveSessionCollector::durationLabel(3 * 86400 + 4 * 3600 + 30 * 60));
    }

    public function test_traffic_is_formatted_without_the_intl_extension(): void
    {
        // Number::fileSize() would need ext-intl, which this deployment has not
        // got — the page must still render a readable byte count.
        $this->assertSame('0 B', LiveSessionCollector::bytesLabel(null));
        $this->assertSame('512 B', LiveSessionCollector::bytesLabel(512));
        $this->assertSame('1.0 KB', LiveSessionCollector::bytesLabel(1024));
        $this->assertSame('1.0 MB', LiveSessionCollector::bytesLabel(1024 ** 2));
        $this->assertSame('2.5 GB', LiveSessionCollector::bytesLabel((int) (2.5 * 1024 ** 3)));
        $this->assertSame('1.0 TB', LiveSessionCollector::bytesLabel(1024 ** 4));
    }
}
