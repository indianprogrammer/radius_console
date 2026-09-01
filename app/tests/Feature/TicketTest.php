<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffGroup;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tickets: single / multi / group assignment, reassignment and the audit trail.
 */
class TicketTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Ticket ISP', 'domain' => 'tickets.test', 'slug' => 'tickets', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function staff(string $name, array $overrides = []): Staff
    {
        return Staff::create(array_merge([
            'tenant_id'       => $this->tenant->id,
            'code'            => Staff::nextCode($this->tenant->id),
            'name'            => $name,
            'role'            => 'technician',
            'employment_type' => 'full_time',
            'status'          => 'active',
            'basic_salary'    => 20000,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'subject'  => 'No internet since morning',
            'category' => 'fault',
            'priority' => 'high',
            'status'   => 'open',
            'source'   => 'phone',
        ], $overrides);
    }

    private function makeTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge($this->payload(), [
            'tenant_id' => $this->tenant->id,
            'number'    => Ticket::nextNumber($this->tenant->id),
        ], $overrides));
    }

    public function test_ticket_is_created_with_a_number_and_an_sla_due_date(): void
    {
        $this->post($this->url('/tickets'), $this->payload(['number' => '']))->assertRedirect();

        $ticket = Ticket::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('TKT-000001', $ticket->number);
        // priority=high → 24h SLA.
        $this->assertNotNull($ticket->due_at);
        $this->assertTrue($ticket->due_at->greaterThan(now()));

        // A "created" event is always recorded.
        $this->assertSame(1, TicketEvent::where('ticket_id', $ticket->id)->where('type', 'created')->count());
    }

    public function test_assigning_an_owner_also_records_them_as_an_assignee_and_moves_the_status(): void
    {
        $ticket = $this->makeTicket();
        $ravi   = $this->staff('Ravi');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), [
            'assigned_staff_id' => $ravi->id,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($ravi->id, $ticket->assigned_staff_id);
        $this->assertSame('assigned', $ticket->status);
        $this->assertNotNull($ticket->assigned_at);

        // The owner is always in the assignee set, flagged primary.
        $this->assertTrue($ticket->assignees->contains($ravi->id));
        $this->assertEquals(1, $ticket->assignees()->wherePivot('is_primary', true)->count());

        $this->assertSame(1, TicketEvent::where('ticket_id', $ticket->id)->where('type', 'assigned')->count());
    }

    public function test_a_ticket_can_be_assigned_to_several_staff_at_once(): void
    {
        $ticket = $this->makeTicket();
        $a = $this->staff('A');
        $b = $this->staff('B');
        $c = $this->staff('C');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), [
            'assigned_staff_id' => $a->id,
            'assignee_ids'      => [$b->id, $c->id],
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertCount(3, $ticket->assignees);
        // Only the owner is primary.
        $this->assertSame($a->id, (int) $ticket->assignees()->wherePivot('is_primary', true)->firstOrFail()->id);
    }

    public function test_assigning_a_group_expands_its_members(): void
    {
        $ticket = $this->makeTicket();
        $group  = StaffGroup::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Field Techs — North', 'is_active' => true,
        ]);

        $one = $this->staff('One');
        $two = $this->staff('Two');
        $gone = $this->staff('Resigned', ['status' => 'resigned']);

        foreach ([$one, $two, $gone] as $m) {
            $group->members()->attach($m->id, ['tenant_id' => $this->tenant->id]);
        }

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), [
            'staff_group_id' => $group->id,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($group->id, $ticket->staff_group_id);
        // Resigned members are not assignable, so only the two active ones land.
        $this->assertCount(2, $ticket->assignees);
        $this->assertTrue($ticket->assignees->contains($one->id));
        $this->assertFalse($ticket->assignees->contains($gone->id));
    }

    public function test_reassignment_keeps_collaborators_and_logs_from_and_to(): void
    {
        $ticket = $this->makeTicket();
        $old    = $this->staff('Old Owner');
        $helper = $this->staff('Helper');
        $new    = $this->staff('New Owner');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), [
            'assigned_staff_id' => $old->id,
            'assignee_ids'      => [$helper->id],
        ])->assertRedirect();

        $this->post($this->url('/tickets/' . $ticket->id . '/reassign'), [
            'assigned_staff_id' => $new->id,
            'note'              => 'Escalated to the senior technician',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($new->id, $ticket->assigned_staff_id);
        // Old owner and helper stay on the ticket as collaborators.
        $this->assertTrue($ticket->assignees->contains($helper->id));
        $this->assertTrue($ticket->assignees->contains($old->id));
        // Exactly one primary, and it is the new owner.
        $this->assertSame($new->id, (int) $ticket->assignees()->wherePivot('is_primary', true)->firstOrFail()->id);

        $event = TicketEvent::where('ticket_id', $ticket->id)->where('type', 'reassigned')->firstOrFail();
        $this->assertSame($old->id, (int) $event->from_staff_id);
        $this->assertSame($new->id, (int) $event->to_staff_id);
        $this->assertSame('Escalated to the senior technician', $event->note);
    }

    public function test_reassigning_to_the_current_owner_is_rejected(): void
    {
        $ticket = $this->makeTicket();
        $ravi   = $this->staff('Ravi');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), ['assigned_staff_id' => $ravi->id]);

        $this->post($this->url('/tickets/' . $ticket->id . '/reassign'), ['assigned_staff_id' => $ravi->id])
            ->assertSessionHasErrors('assigned_staff_id');
    }

    public function test_the_owner_cannot_be_removed_as_an_assignee(): void
    {
        $ticket = $this->makeTicket();
        $ravi   = $this->staff('Ravi');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), ['assigned_staff_id' => $ravi->id]);

        $this->delete($this->url('/tickets/' . $ticket->id . '/assignees/' . $ravi->id))
            ->assertSessionHasErrors('ticket');

        $this->assertTrue($ticket->refresh()->assignees->contains($ravi->id));
    }

    public function test_a_collaborator_can_be_removed(): void
    {
        $ticket = $this->makeTicket();
        $owner  = $this->staff('Owner');
        $helper = $this->staff('Helper');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), [
            'assigned_staff_id' => $owner->id,
            'assignee_ids'      => [$helper->id],
        ]);

        $this->delete($this->url('/tickets/' . $ticket->id . '/assignees/' . $helper->id))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertFalse($ticket->assignees->contains($helper->id));
        $this->assertTrue($ticket->assignees->contains($owner->id));
    }

    public function test_unassigning_returns_the_ticket_to_the_open_queue(): void
    {
        $ticket = $this->makeTicket();
        $ravi   = $this->staff('Ravi');

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), ['assigned_staff_id' => $ravi->id]);
        $this->assertSame('assigned', $ticket->refresh()->status);

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), ['assigned_staff_id' => ''])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertNull($ticket->assigned_staff_id);
        $this->assertSame('open', $ticket->status);
        $this->assertCount(0, $ticket->assignees);
        $this->assertSame(1, TicketEvent::where('ticket_id', $ticket->id)->where('type', 'unassigned')->count());
    }

    public function test_staff_from_another_tenant_cannot_be_assigned(): void
    {
        $ticket = $this->makeTicket();

        $other = Tenant::create([
            'name' => 'Other', 'domain' => 'other-tickets.test', 'slug' => 'othertkt', 'status' => 'active',
        ]);
        $outsider = Staff::create([
            'tenant_id' => $other->id, 'code' => 'ST-9999', 'name' => 'Outsider',
            'role' => 'technician', 'employment_type' => 'full_time', 'status' => 'active', 'basic_salary' => 1,
        ]);

        $this->post($this->url('/tickets/' . $ticket->id . '/assign'), [
            'assigned_staff_id' => $outsider->id,
        ])->assertSessionHasErrors('assigned_staff_id');

        $this->assertNull($ticket->refresh()->assigned_staff_id);
    }

    public function test_resolving_a_ticket_stamps_the_resolution_time_and_logs_it(): void
    {
        $ticket = $this->makeTicket();

        $this->put($this->url('/tickets/' . $ticket->id), $this->payload([
            'status'     => 'resolved',
            'resolution' => 'Replaced the patch cord at the customer end.',
        ]))->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertFalse($ticket->isOpen());
        $this->assertSame(1, TicketEvent::where('ticket_id', $ticket->id)->where('type', 'resolved')->count());
    }

    public function test_the_listing_can_filter_the_unassigned_queue(): void
    {
        $assigned = $this->makeTicket(['subject' => 'Has an owner']);
        $this->makeTicket(['subject' => 'Nobody owns this']);

        $ravi = $this->staff('Ravi');
        $this->post($this->url('/tickets/' . $assigned->id . '/assign'), ['assigned_staff_id' => $ravi->id]);

        $this->get($this->url('/tickets?unassigned=1'))
            ->assertOk()
            ->assertSee('Nobody owns this')
            ->assertDontSee('Has an owner');
    }
}
