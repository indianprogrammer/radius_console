<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffGroup;
use App\Models\Ticket;
use App\Models\TicketEvent;
use Illuminate\Support\Facades\DB;

/**
 * The single place that mutates ticket assignment.
 *
 * Rules enforced here rather than in the controller, so the API, a queue job
 * and the UI cannot drift apart:
 *  1. The owner (`assigned_staff_id`) is ALWAYS also a row in
 *     `ticket_assignees` with `is_primary = true` — exactly one such row.
 *  2. Every change appends a `ticket_events` row (`assigned` / `reassigned` /
 *     `unassigned`) capturing from → to, so an escalation is auditable.
 *  3. An `open` ticket flips to `assigned` on first assignment; a ticket that
 *     is already in progress keeps its status.
 *  4. Only employees of the SAME tenant, in an assignable status, are accepted.
 */
final class TicketAssigner
{
    /**
     * Assign or reassign the owner, optionally with collaborators / a group.
     *
     * @param int|null           $ownerId       New primary owner (null = unassign).
     * @param array<int|string>  $collaborators Extra staff ids to keep on the ticket.
     * @param int|null           $groupId       Staff group to expand into collaborators.
     */
    public function assign(
        Ticket $ticket,
        ?int $ownerId,
        array $collaborators = [],
        ?int $groupId = null,
        ?string $note = null,
        ?string $actor = null,
    ): Ticket {
        $previousOwnerId = $ticket->assigned_staff_id ? (int) $ticket->assigned_staff_id : null;

        $ownerId = $this->validStaffId($ticket->tenant_id, $ownerId);
        $ids     = $this->resolveAssigneeIds($ticket->tenant_id, $ownerId, $collaborators, $groupId);

        DB::transaction(function () use ($ticket, $ownerId, $ids, $groupId, $previousOwnerId, $note, $actor) {
            $ticket->staff_group_id    = $groupId;
            $ticket->assigned_staff_id = $ownerId;
            $ticket->assigned_at       = $ownerId ? now() : null;

            // First assignment moves the ticket out of the unassigned queue.
            if ($ownerId && $ticket->status === 'open') {
                $ticket->status = 'assigned';
            }
            // Losing every assignee sends a still-open ticket back to the queue.
            if (!$ownerId && $ticket->status === 'assigned') {
                $ticket->status = 'open';
            }

            $ticket->save();

            $this->syncAssignees($ticket, $ownerId, $ids);
            $this->recordChange($ticket, $previousOwnerId, $ownerId, $note, $actor);
        });

        return $ticket->refresh();
    }

    /**
     * Hand the ticket to a different owner. Thin wrapper over `assign()` that
     * keeps the existing collaborators, since a reassignment usually means
     * "someone else now leads the same crew".
     */
    public function reassign(Ticket $ticket, int $newOwnerId, ?string $note = null, ?string $actor = null): Ticket
    {
        $keep = $ticket->assignees()
            ->pluck('staff.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->assign($ticket, $newOwnerId, $keep, $ticket->staff_group_id, $note, $actor);
    }

    /** Add collaborators without touching the owner. */
    public function addCollaborators(Ticket $ticket, array $staffIds, ?string $actor = null): Ticket
    {
        $owner = $ticket->assigned_staff_id ? (int) $ticket->assigned_staff_id : null;
        $keep  = $ticket->assignees()->pluck('staff.id')->map(fn ($id) => (int) $id)->all();

        return $this->assign($ticket, $owner, array_merge($keep, $staffIds), $ticket->staff_group_id, null, $actor);
    }

    /**
     * Remove one collaborator. Removing the OWNER is refused — use
     * `reassign()` or `assign(null)`, so a ticket can never end up with
     * collaborators but no accountable owner.
     */
    public function removeCollaborator(Ticket $ticket, int $staffId): Ticket
    {
        if ((int) $ticket->assigned_staff_id === $staffId) {
            return $ticket;
        }

        $ticket->assignees()->detach($staffId);

        return $ticket->refresh();
    }

    /**
     * Final assignee id set: owner + explicit collaborators + group members,
     * de-duplicated and filtered to assignable staff of this tenant.
     *
     * @return list<int>
     */
    private function resolveAssigneeIds(
        int|string $tenantId,
        ?int $ownerId,
        array $collaborators,
        ?int $groupId,
    ): array {
        $ids = array_map('intval', array_filter($collaborators, fn ($v) => $v !== null && $v !== ''));

        if ($groupId) {
            $group = StaffGroup::where('tenant_id', $tenantId)->find($groupId);
            if ($group) {
                // Expanded NOW, so later membership changes don't rewrite history.
                $ids = array_merge($ids, $group->assignableMembers()->pluck('id')->map(fn ($i) => (int) $i)->all());
            }
        }

        if ($ownerId) {
            $ids[] = $ownerId;
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        // Cross-tenant or resigned ids are dropped rather than erroring: the
        // caller's intent (assign these people) is still honoured for the rest.
        return Staff::where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->whereIn('status', ['active', 'on_leave'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Null unless the id is an assignable member of this tenant. */
    private function validStaffId(int|string $tenantId, ?int $staffId): ?int
    {
        if (!$staffId) {
            return null;
        }

        $ok = Staff::where('tenant_id', $tenantId)
            ->whereKey($staffId)
            ->whereIn('status', ['active', 'on_leave'])
            ->exists();

        return $ok ? $staffId : null;
    }

    /** Replace the assignee set, keeping exactly one `is_primary` row. */
    private function syncAssignees(Ticket $ticket, ?int $ownerId, array $ids): void
    {
        $pivot = [];
        foreach ($ids as $id) {
            $pivot[$id] = [
                'tenant_id'   => $ticket->tenant_id,
                'is_primary'  => $id === $ownerId,
                'assigned_at' => now(),
            ];
        }

        // sync() removes anyone no longer in the set and updates the pivot of
        // those who remain, so a demoted owner loses `is_primary` correctly.
        $ticket->assignees()->sync($pivot);
    }

    /** Append the audit row describing the ownership change. */
    private function recordChange(
        Ticket $ticket,
        ?int $from,
        ?int $to,
        ?string $note,
        ?string $actor,
    ): void {
        if ($from === $to) {
            // No ownership change; only record an explicit note.
            if ($note) {
                TicketEvent::create([
                    'tenant_id' => $ticket->tenant_id,
                    'ticket_id' => $ticket->id,
                    'type'      => 'comment',
                    'note'      => $note,
                    'actor'     => $actor,
                ]);
            }

            return;
        }

        $type = match (true) {
            $from === null => 'assigned',
            $to === null   => 'unassigned',
            default        => 'reassigned',
        };

        TicketEvent::create([
            'tenant_id'     => $ticket->tenant_id,
            'ticket_id'     => $ticket->id,
            'type'          => $type,
            'from_staff_id' => $from,
            'to_staff_id'   => $to,
            'note'          => $note,
            'actor'         => $actor,
        ]);
    }
}
