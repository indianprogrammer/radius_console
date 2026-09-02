<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logs menu: the channel pages, the filters, the auth-event listeners, the
 * model observer that feeds Audit Logs, the secret redaction required by
 * SRD §9.4, immutability and tenant isolation.
 */
class LogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Logs ISP', 'domain' => 'logs.test', 'slug' => 'logs', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function logger(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }

    private function entry(array $overrides = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'channel'   => 'audit',
            'action'    => 'created',
            'actor'     => 'Ops Admin',
            'status'    => 'success',
        ], $overrides));
    }

    public function test_index_redirects_to_the_audit_channel(): void
    {
        $this->get($this->url('/logs'))
            ->assertRedirect($this->url('/logs/audit'));
    }

    public function test_every_channel_renders_with_its_label(): void
    {
        foreach (ActivityLog::CHANNELS as $channel => $label) {
            $this->get($this->url('/logs/' . $channel))
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_an_unknown_channel_is_not_found(): void
    {
        $this->get($this->url('/logs/telepathy'))->assertNotFound();
    }

    public function test_a_channel_only_shows_its_own_entries(): void
    {
        $this->entry(['channel' => 'audit', 'message' => 'An audit entry']);
        $this->entry(['channel' => 'sms', 'action' => 'sent', 'message' => 'An SMS entry']);

        $this->get($this->url('/logs/audit'))
            ->assertOk()
            ->assertSee('An audit entry')
            ->assertDontSee('An SMS entry');

        $this->get($this->url('/logs/sms'))
            ->assertOk()
            ->assertSee('An SMS entry')
            ->assertDontSee('An audit entry');
    }

    public function test_the_listing_can_be_filtered(): void
    {
        $this->entry(['action' => 'created', 'message' => 'Created something']);
        $this->entry(['action' => 'deleted', 'status' => 'failed', 'message' => 'Deletion refused']);

        $this->get($this->url('/logs/audit?action=deleted'))
            ->assertOk()->assertSee('Deletion refused')->assertDontSee('Created something');

        $this->get($this->url('/logs/audit?status=failed'))
            ->assertOk()->assertSee('Deletion refused')->assertDontSee('Created something');

        $this->get($this->url('/logs/audit?failed=1'))
            ->assertOk()->assertSee('Deletion refused')->assertDontSee('Created something');

        $this->get($this->url('/logs/audit?q=refused'))
            ->assertOk()->assertSee('Deletion refused')->assertDontSee('Created something');
    }

    public function test_the_date_range_includes_the_whole_end_day(): void
    {
        // 09:30 on the "to" date — a bare Y-m-d comparison would drop this row.
        $this->entry(['message' => 'Morning entry'])
            ->forceFill(['created_at' => now()->subDay()->setTime(9, 30)])->save();

        $this->entry(['message' => 'Ancient entry'])
            ->forceFill(['created_at' => now()->subMonths(2)])->save();

        $to = now()->subDay()->toDateString();

        $this->get($this->url("/logs/audit?from={$to}&to={$to}"))
            ->assertOk()
            ->assertSee('Morning entry')
            ->assertDontSee('Ancient entry');
    }

    public function test_the_action_filter_only_offers_actions_present_in_the_channel(): void
    {
        $this->entry(['action' => 'created']);
        $this->entry(['channel' => 'sms', 'action' => 'sent']);

        $this->get($this->url('/logs/audit'))
            ->assertOk()
            ->assertViewHas('actions', fn (array $actions) =>
                array_key_exists('created', $actions) && !array_key_exists('sent', $actions));
    }

    public function test_the_header_cards_count_the_whole_channel(): void
    {
        $this->entry(['status' => 'failed']);
        $this->entry();
        $this->entry(['channel' => 'sms']);

        $this->get($this->url('/logs/audit'))
            ->assertOk()
            ->assertViewHas('totals', fn (array $totals) =>
                $totals['total'] === 2 && $totals['failed'] === 1 && $totals['today'] === 2);
    }

    public function test_logs_are_read_only(): void
    {
        // No write verbs exist on the log routes — an editable audit trail is
        // not an audit trail (SRD §9.8).
        $this->post($this->url('/logs/audit'), [])->assertMethodNotAllowed();
        $this->put($this->url('/logs/audit'), [])->assertMethodNotAllowed();
        $this->delete($this->url('/logs/audit'))->assertMethodNotAllowed();
    }

    public function test_secrets_are_redacted_from_the_payload(): void
    {
        $this->logger()->log('audit', 'created', [
            'tenant_id' => $this->tenant->id,
            'payload'   => [
                'username'       => 'john.doe',
                'password'       => 'sup3rs3cret',
                'pppoe_password' => 'another-one',
                'api_key'        => 'abcd1234',
                'nested'         => ['shared_secret' => 'radius-secret', 'nas_ip' => '10.0.0.1'],
            ],
        ]);

        $payload = ActivityLog::where('tenant_id', $this->tenant->id)->firstOrFail()->payload;

        $this->assertSame('john.doe', $payload['username']);
        $this->assertSame('10.0.0.1', $payload['nested']['nas_ip']);

        foreach ([$payload['password'], $payload['pppoe_password'], $payload['api_key'], $payload['nested']['shared_secret']] as $value) {
            $this->assertSame('[redacted]', $value);
        }
    }

    public function test_a_successful_login_is_recorded_in_login_history(): void
    {
        $user = $this->user();

        event(new Login('web', $user, false));

        $entry = ActivityLog::where('channel', 'login')->firstOrFail();
        $this->assertSame('login', $entry->action);
        $this->assertSame('success', $entry->status);
        $this->assertSame($user->name, $entry->actor);
    }

    public function test_a_failed_login_is_recorded_without_the_submitted_password(): void
    {
        // A rejected attempt for an unknown account has no user to take the
        // tenant from, so it falls back to the one the request resolved — hit a
        // tenant page first, exactly as a real sign-in attempt would.
        $this->get($this->url('/logs/login_fail'))->assertOk();

        event(new Failed('web', null, ['email' => 'intruder@example.test', 'password' => 'guess']));

        $entry = ActivityLog::where('channel', 'login_fail')->firstOrFail();
        $this->assertSame('login_failed', $entry->action);
        $this->assertSame('failed', $entry->status);
        $this->assertSame('intruder@example.test', $entry->actor);

        // The whole point: the attempted password must not be stored anywhere.
        $this->assertStringNotContainsString('guess', json_encode($entry->getAttributes()));
    }

    public function test_a_model_change_is_audited_automatically(): void
    {
        $item = Inventory::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'LOG-1', 'name' => 'Logged Router',
            'category' => 'physical', 'stock_quantity' => 5, 'reorder_point' => 1,
            'cost_price' => 100, 'sale_price' => 150, 'is_active' => true,
        ]);

        $created = ActivityLog::where('object_type', Inventory::class)->where('action', 'created')->firstOrFail();
        $this->assertSame('audit', $created->channel);
        $this->assertSame((string) $item->id, $created->object_id);
        $this->assertSame('Logged Router', $created->object_label);

        $item->update(['stock_quantity' => 2]);

        // The trail records WHICH attributes changed, not their values.
        $updated = ActivityLog::where('action', 'updated')->firstOrFail();
        $this->assertContains('stock_quantity', $updated->payload['changed']);

        $item->delete();
        $this->assertDatabaseHas('audit_log', ['action' => 'deleted', 'object_type' => Inventory::class]);
    }

    public function test_a_save_that_changes_nothing_is_not_logged(): void
    {
        $item = Inventory::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'LOG-2', 'name' => 'Quiet Router',
            'category' => 'physical', 'stock_quantity' => 5, 'reorder_point' => 1,
            'cost_price' => 100, 'sale_price' => 150, 'is_active' => true,
        ]);

        $item->update(['stock_quantity' => 5]);

        $this->assertSame(0, ActivityLog::where('action', 'updated')->count());
    }

    public function test_a_message_log_stores_the_recipient_and_outcome(): void
    {
        $this->logger()->message('sms', '9876500001', 'failed', [
            'tenant_id' => $this->tenant->id,
            'message'   => 'Gateway rejected the number.',
        ]);

        $entry = ActivityLog::where('channel', 'sms')->firstOrFail();
        $this->assertSame('failed', $entry->action);
        $this->assertSame('failed', $entry->status);
        $this->assertSame('9876500001', $entry->object_label);

        $this->get($this->url('/logs/sms'))->assertOk()->assertSee('9876500001');
    }

    public function test_logs_do_not_leak_between_tenants(): void
    {
        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-logs.test', 'slug' => 'otherlogs', 'status' => 'active',
        ]);

        $this->entry(['message' => 'Mine only']);
        $this->entry(['tenant_id' => $other->id, 'message' => 'Theirs only']);

        $this->get($this->url('/logs/audit'))
            ->assertOk()
            ->assertSee('Mine only')
            ->assertDontSee('Theirs only');
    }

    public function test_a_log_write_failure_never_breaks_the_action(): void
    {
        // tenant_id is a NOT NULL FK, so this insert cannot succeed.
        $this->assertNull($this->logger()->log('audit', 'created', ['tenant_id' => null]));
    }

    /** A console login, needed by the auth-event listeners. */
    private function user(): \App\Models\User
    {
        $user = new \App\Models\User();
        $user->forceFill([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Ops Admin',
            'email'     => 'ops@logs.test',
            'password'  => bcrypt('secret-password'),
        ])->save();

        return $user;
    }
}
