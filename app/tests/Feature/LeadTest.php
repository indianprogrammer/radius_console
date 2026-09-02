<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Staff;
use App\Models\Subscriber;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales pipeline: CRUD, the per-tenant numbering, the stage transitions that
 * LeadService owns (contact → contacted, won/lost stamps, follow-up clearing),
 * the quotation hand-off, the follow-up queue and tenant isolation.
 */
class LeadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Sales ISP', 'domain' => 'sales.test', 'slug' => 'sales', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'Ramesh Kumar',
            'company'         => 'Kumar Traders',
            'phone'           => '9876500001',
            'email'           => 'ramesh@example.test',
            'source'          => 'phone',
            'status'          => 'new',
            'rating'          => 'warm',
            'estimated_value' => 7500,
        ], $overrides);
    }

    private function lead(array $overrides = []): Lead
    {
        return Lead::create(array_merge(
            ['tenant_id' => $this->tenant->id, 'number' => Lead::nextNumber($this->tenant->id)],
            $this->payload($overrides),
        ));
    }

    private function plan(): Plan
    {
        return Plan::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Fibre 100', 'price' => 800,
            'duration' => 1, 'duration_unit' => 'months',
        ]);
    }

    private function staff(): Staff
    {
        return Staff::create([
            'tenant_id' => $this->tenant->id, 'code' => 'EMP-001', 'name' => 'Sales Sam',
            'role' => 'sales', 'employment_type' => 'full_time', 'status' => 'active',
        ]);
    }

    private function service(): \App\Services\LeadService
    {
        return app(\App\Services\LeadService::class);
    }

    public function test_a_lead_is_created_with_an_auto_generated_number_and_a_trail(): void
    {
        $this->post($this->url('/leads'), $this->payload())->assertRedirect();

        $lead = Lead::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('LEAD-000001', $lead->number);
        $this->assertSame('new', $lead->status);

        // The next one continues the sequence.
        $this->post($this->url('/leads'), $this->payload(['name' => 'Second']))->assertRedirect();
        $this->assertSame('LEAD-000002', Lead::where('name', 'Second')->firstOrFail()->number);

        // Creation is on the trail, so "where did this lead come from" is answerable.
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'created']);
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->post($this->url('/leads'), [])
            ->assertSessionHasErrors(['name', 'source', 'status', 'rating']);
    }

    public function test_stage_source_and_rating_must_be_known_values(): void
    {
        $this->post($this->url('/leads'), $this->payload([
            'status' => 'maybe', 'source' => 'telepathy', 'rating' => 'lukewarm',
        ]))->assertSessionHasErrors(['status', 'source', 'rating']);
    }

    public function test_number_must_be_unique_per_tenant_only(): void
    {
        $this->post($this->url('/leads'), $this->payload(['number' => 'LEAD-A']))->assertRedirect();

        $this->post($this->url('/leads'), $this->payload(['name' => 'Dup', 'number' => 'LEAD-A']))
            ->assertSessionHasErrors('number');

        $other = Tenant::create([
            'name' => 'Other ISP', 'domain' => 'other-sales.test', 'slug' => 'other-sales', 'status' => 'active',
        ]);
        $this->post('http://' . $other->domain . '/leads', $this->payload(['number' => 'LEAD-A']))
            ->assertRedirect();

        $this->assertSame(2, Lead::where('number', 'LEAD-A')->count());
    }

    public function test_logging_a_call_advances_a_new_lead_to_contacted(): void
    {
        $lead = $this->lead();

        $this->post($this->url('/leads/' . $lead->id . '/activity'), [
            'type' => 'call', 'note' => 'Discussed the 100 Mbps plan.',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('contacted', $lead->status);
        $this->assertNotNull($lead->last_contacted_at);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'call']);
    }

    public function test_a_note_is_not_contact_and_does_not_move_the_stage(): void
    {
        $lead = $this->lead();

        $this->post($this->url('/leads/' . $lead->id . '/activity'), ['type' => 'note', 'note' => 'Left a voicemail.'])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('new', $lead->status);
        $this->assertNull($lead->last_contacted_at);
    }

    public function test_contact_on_an_advanced_lead_does_not_pull_it_back(): void
    {
        $lead = $this->lead(['status' => 'negotiation']);

        $this->service()->logActivity($lead, 'call');

        $this->assertSame('negotiation', $lead->refresh()->status);
    }

    public function test_a_stage_change_is_recorded_on_the_trail(): void
    {
        $lead = $this->lead();

        $this->put($this->url('/leads/' . $lead->id), $this->payload(['status' => 'qualified']))
            ->assertRedirect();

        $this->assertSame('qualified', $lead->refresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'type' => 'status', 'from_status' => 'new', 'to_status' => 'qualified',
        ]);
    }

    public function test_an_owner_change_is_recorded_on_the_trail(): void
    {
        $lead = $this->lead();
        $staff = $this->staff();

        $this->put($this->url('/leads/' . $lead->id), $this->payload(['assigned_staff_id' => $staff->id]))
            ->assertRedirect();

        $this->assertSame($staff->id, $lead->refresh()->assigned_staff_id);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'type' => 'assigned', 'to_staff_id' => $staff->id,
        ]);
    }

    public function test_winning_a_lead_stamps_it_and_clears_the_follow_up(): void
    {
        $lead = $this->lead(['next_follow_up_at' => now()->addDay()]);
        $subscriber = $this->subscriber();

        $this->post($this->url('/leads/' . $lead->id . '/win'), [
            'subscriber_id' => $subscriber->id, 'note' => 'Signed the CAF.',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('won', $lead->status);
        $this->assertNotNull($lead->won_at);
        $this->assertSame($subscriber->id, $lead->subscriber_id);
        // A closed lead must leave the follow-up queue.
        $this->assertNull($lead->next_follow_up_at);
        $this->assertFalse($lead->isOpen());
    }

    public function test_losing_a_lead_requires_a_reason_and_stamps_it(): void
    {
        $lead = $this->lead(['next_follow_up_at' => now()->addDay()]);

        $this->post($this->url('/leads/' . $lead->id . '/lose'), [])
            ->assertSessionHasErrors('lost_reason');

        $this->post($this->url('/leads/' . $lead->id . '/lose'), ['lost_reason' => 'Chose a competitor'])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('lost', $lead->status);
        $this->assertSame('Chose a competitor', $lead->lost_reason);
        $this->assertNotNull($lead->lost_at);
        $this->assertNull($lead->next_follow_up_at);
    }

    public function test_reopening_a_closed_lead_clears_its_closure_stamps(): void
    {
        $lead = $this->lead();
        $this->service()->markLost($lead, 'Too expensive');
        $this->assertNotNull($lead->refresh()->lost_at);

        $this->service()->changeStatus($lead, 'negotiation');

        $lead->refresh();
        $this->assertSame('negotiation', $lead->status);
        $this->assertNull($lead->lost_at);
        $this->assertNull($lead->lost_reason);
        $this->assertTrue($lead->isOpen());
    }

    public function test_raising_a_quotation_links_it_and_moves_the_lead_to_proposal(): void
    {
        $plan = $this->plan();
        $lead = $this->lead(['plan_id' => $plan->id, 'status' => 'qualified']);

        $this->post($this->url('/leads/' . $lead->id . '/quote'))->assertRedirect();

        $lead->refresh();
        $quote = Quote::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame($quote->id, $lead->quote_id);
        $this->assertSame('proposal', $lead->status);
        $this->assertSame(Quote::TYPE_QUOTATION, $quote->type);
        // Seeded from the plan price so the document is not empty.
        $this->assertSame(1, $quote->items()->count());
        $this->assertSame(800.0, $quote->items()->first()->unit_price);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'quoted']);
    }

    public function test_a_lead_cannot_be_quoted_twice(): void
    {
        $lead = $this->lead(['plan_id' => $this->plan()->id]);

        $this->post($this->url('/leads/' . $lead->id . '/quote'))->assertRedirect();
        $this->post($this->url('/leads/' . $lead->id . '/quote'))
            ->assertSessionHasErrors('lead');

        $this->assertSame(1, Quote::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_closed_lead_cannot_be_quoted(): void
    {
        $lead = $this->lead(['plan_id' => $this->plan()->id]);
        $this->service()->markLost($lead, 'No coverage');

        $this->post($this->url('/leads/' . $lead->id . '/quote'))
            ->assertSessionHasErrors('lead');

        $this->assertSame(0, Quote::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_scheduling_a_follow_up_puts_the_lead_in_the_due_queue(): void
    {
        $due = $this->lead(['name' => 'Needs Calling']);
        $this->service()->scheduleFollowUp($due, now()->subHour());

        $notDue = $this->lead(['name' => 'Called Already']);
        $this->service()->scheduleFollowUp($notDue, now()->addWeek());

        $this->assertTrue($due->refresh()->isFollowUpDue());
        $this->assertFalse($notDue->refresh()->isFollowUpDue());

        $this->get($this->url('/leads?due=1'))
            ->assertOk()
            ->assertSee('Needs Calling')
            ->assertDontSee('Called Already');
    }

    public function test_a_won_lead_never_appears_in_the_due_queue(): void
    {
        $lead = $this->lead(['name' => 'Closed Deal']);
        $this->service()->scheduleFollowUp($lead, now()->subDay());
        $this->service()->markWon($lead);

        $this->get($this->url('/leads?due=1'))
            ->assertOk()
            ->assertDontSee('Closed Deal');
    }

    public function test_filters_narrow_the_list(): void
    {
        $this->lead(['name' => 'Hot Prospect', 'rating' => 'hot', 'source' => 'referral']);
        $this->lead(['name' => 'Cold Prospect', 'rating' => 'cold', 'source' => 'website']);

        $this->get($this->url('/leads?rating=hot'))
            ->assertOk()->assertSee('Hot Prospect')->assertDontSee('Cold Prospect');

        $this->get($this->url('/leads?source=website'))
            ->assertOk()->assertSee('Cold Prospect')->assertDontSee('Hot Prospect');

        $this->get($this->url('/leads?q=Hot'))
            ->assertOk()->assertSee('Hot Prospect')->assertDontSee('Cold Prospect');
    }

    public function test_the_pipeline_total_counts_open_leads_only(): void
    {
        $this->lead(['estimated_value' => 1000]);
        $this->lead(['estimated_value' => 500]);
        $won = $this->lead(['estimated_value' => 9999]);
        $this->service()->markWon($won);

        // Only the two open leads: won money belongs to invoices, not the pipeline.
        $this->get($this->url('/leads'))->assertOk()->assertSee('1,500.00');
    }

    public function test_the_win_rate_only_counts_decided_leads(): void
    {
        $won = $this->lead();
        $this->service()->markWon($won);
        $lost = $this->lead();
        $this->service()->markLost($lost, 'price');
        // Two open leads must not drag the rate down — they have no verdict yet.
        $this->lead();
        $this->lead();

        $this->get($this->url('/leads'))->assertOk()->assertSee('50.0%');
    }

    // ── Pipeline board ───────────────────────────────────────────────────

    public function test_the_board_shows_open_leads_grouped_by_stage(): void
    {
        $this->lead(['name' => 'Fresh Prospect', 'status' => 'new']);
        $this->lead(['name' => 'Talking Terms', 'status' => 'negotiation']);

        $this->get($this->url('/leads/board'))
            ->assertOk()
            ->assertSee('Pipeline Board')
            ->assertSee('Fresh Prospect')
            ->assertSee('Talking Terms')
            // Every open stage gets a column, even an empty one.
            ->assertSee('Qualified')
            ->assertSee('Proposal Sent');
    }

    public function test_the_board_excludes_closed_leads(): void
    {
        $won = $this->lead(['name' => 'Won Deal']);
        $this->service()->markWon($won);
        $lost = $this->lead(['name' => 'Lost Deal']);
        $this->service()->markLost($lost, 'price');
        $this->lead(['name' => 'Still Open']);

        $this->get($this->url('/leads/board'))
            ->assertOk()
            ->assertSee('Still Open')
            ->assertDontSee('Won Deal')
            ->assertDontSee('Lost Deal');
    }

    public function test_the_board_column_totals_sum_the_stage_value(): void
    {
        $this->lead(['status' => 'qualified', 'estimated_value' => 1200]);
        $this->lead(['status' => 'qualified', 'estimated_value' => 800]);

        $this->get($this->url('/leads/board'))->assertOk()->assertSee('2,000.00');
    }

    public function test_the_board_honours_the_owner_and_rating_filters(): void
    {
        $staff = $this->staff();
        $this->lead(['name' => 'Mine Hot', 'rating' => 'hot', 'assigned_staff_id' => $staff->id]);
        $this->lead(['name' => 'Someone Elses', 'rating' => 'cold']);

        $this->get($this->url('/leads/board?staff_id=' . $staff->id))
            ->assertOk()->assertSee('Mine Hot')->assertDontSee('Someone Elses');

        $this->get($this->url('/leads/board?rating=cold'))
            ->assertOk()->assertSee('Someone Elses')->assertDontSee('Mine Hot');

        $this->get($this->url('/leads/board?unassigned=1'))
            ->assertOk()->assertSee('Someone Elses')->assertDontSee('Mine Hot');
    }

    public function test_moving_a_card_changes_the_stage_and_records_the_trail(): void
    {
        $lead = $this->lead(['status' => 'contacted']);

        $this->postJson($this->url('/leads/' . $lead->id . '/stage'), ['status' => 'negotiation'])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'negotiation']);

        $this->assertSame('negotiation', $lead->refresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'type' => 'status',
            'from_status' => 'contacted', 'to_status' => 'negotiation',
        ]);
    }

    /**
     * Closing a deal needs its own input (a subscriber link, or a reason for the
     * funnel), so a drag must not be able to do it silently.
     */
    public function test_a_card_cannot_be_dragged_to_won_or_lost(): void
    {
        $lead = $this->lead();

        $this->postJson($this->url('/leads/' . $lead->id . '/stage'), ['status' => 'won'])
            ->assertStatus(422);
        $this->postJson($this->url('/leads/' . $lead->id . '/stage'), ['status' => 'lost'])
            ->assertStatus(422);

        $this->assertSame('new', $lead->refresh()->status);
    }

    public function test_an_unknown_stage_is_rejected(): void
    {
        $lead = $this->lead();

        $this->post($this->url('/leads/' . $lead->id . '/stage'), ['status' => 'dreaming'])
            ->assertSessionHasErrors('status');

        $this->assertSame('new', $lead->refresh()->status);
    }

    public function test_another_tenants_card_cannot_be_moved(): void
    {
        $other = Tenant::create([
            'name' => 'Board Rival', 'domain' => 'board-rival.test', 'slug' => 'board-rival', 'status' => 'active',
        ]);
        $foreign = Lead::create(array_merge(
            ['tenant_id' => $other->id, 'number' => 'LEAD-BOARD'],
            $this->payload(),
        ));

        $this->postJson($this->url('/leads/' . $foreign->id . '/stage'), ['status' => 'qualified'])
            ->assertNotFound();

        $this->assertSame('new', $foreign->refresh()->status);
    }

    public function test_another_tenants_lead_is_not_reachable(): void    {
        $other = Tenant::create([
            'name' => 'Rival ISP', 'domain' => 'rival-sales.test', 'slug' => 'rival-sales', 'status' => 'active',
        ]);
        $foreign = Lead::create(array_merge(
            ['tenant_id' => $other->id, 'number' => 'LEAD-FOREIGN'],
            $this->payload(),
        ));

        $this->get($this->url('/leads/' . $foreign->id))->assertNotFound();
        $this->get($this->url('/leads/' . $foreign->id . '/edit'))->assertNotFound();
        $this->put($this->url('/leads/' . $foreign->id), $this->payload())->assertNotFound();
        $this->post($this->url('/leads/' . $foreign->id . '/win'), [])->assertNotFound();
        $this->delete($this->url('/leads/' . $foreign->id))->assertNotFound();
    }

    public function test_a_lead_is_deleted_with_its_trail(): void
    {
        $lead = $this->lead();
        $this->service()->logActivity($lead, 'call');

        $this->delete($this->url('/leads/' . $lead->id))
            ->assertRedirect($this->url('/leads'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('lead_activities', ['lead_id' => $lead->id]);
    }

    /** The index deletes over fetch(), so ajax callers must get JSON not a redirect. */
    public function test_an_ajax_delete_answers_with_json(): void
    {
        $lead = $this->lead();

        $this->deleteJson($this->url('/leads/' . $lead->id))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    private function subscriber(): Subscriber
    {
        return Subscriber::create([
            'tenant_id'       => $this->tenant->id,
            'username'        => 'wonuser',
            'radius_username' => 'sales-wonuser',
            'password_enc'    => 'x',
            'plan_id'         => $this->plan()->id,
            'status'          => 'active',
        ]);
    }
}