<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use App\Models\Staff;
use App\Models\StaffGroup;
use App\Models\Subscriber;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Services\TicketAssigner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tickets / Helpdesk — Support Tickets.
 *
 * All assignment goes through `TicketAssigner` so the owner, the collaborator
 * set and the `ticket_events` audit trail can never diverge. The controller
 * only validates and reports.
 */
final class TicketController extends Controller
{
    public function __construct(private readonly TicketAssigner $assigner) {}

    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $tickets = Ticket::query()
            ->where('tenant_id', tenant_id())
            ->with(['owner', 'group', 'subscriber', 'assignees'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            // "Assigned to" matches collaborators too, not just the owner —
            // a technician must find every ticket they are on.
            ->when($request->query('staff_id'), fn ($q, $id) => $q->where(function ($w) use ($id) {
                $w->where('assigned_staff_id', $id)
                  ->orWhereHas('assignees', fn ($a) => $a->where('staff.id', $id));
            }))
            ->when($request->query('unassigned'), fn ($q) => $q->whereNull('assigned_staff_id'))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhere('subject', 'like', "%{$search}%")
                      ->orWhere('contact_name', 'like', "%{$search}%")
                      ->orWhere('contact_phone', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('tickets.index', [
            'tickets'    => $tickets,
            'search'     => $request->query('q'),
            'status'     => $request->query('status'),
            'priority'   => $request->query('priority'),
            'category'   => $request->query('category'),
            'staffId'    => $request->query('staff_id'),
            'unassigned' => $request->query('unassigned'),
            'staff'      => $this->assignableStaff(),
            'totals'     => $this->summary(),
        ]);
    }

    public function create()
    {
        return view('tickets.create', [
            'ticket' => new Ticket([
                'number'   => Ticket::nextNumber(tenant_id()),
                'category' => 'fault',
                'priority' => 'medium',
                'status'   => 'open',
                'source'   => 'phone',
            ]),
            'staff'       => $this->assignableStaff(),
            'groups'      => $this->groups(),
            'subscribers' => $this->subscribers(),
            'franchises'  => $this->franchises(),
            'selectedAssignees' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $assignees = $data['assignee_ids'] ?? [];
        $ownerId   = $data['assigned_staff_id'] ?? null;
        $groupId   = $data['staff_group_id'] ?? null;
        unset($data['assignee_ids'], $data['assigned_staff_id'], $data['staff_group_id']);

        $data['tenant_id'] = tenant_id();
        $data['number']    = ($data['number'] ?? null) ?: Ticket::nextNumber(tenant_id());
        // No explicit due date → derive one from the priority SLA.
        $data['due_at']    = ($data['due_at'] ?? null) ?: Ticket::slaDueAt($data['priority']);

        $ticket = Ticket::create($data);

        TicketEvent::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'type'      => 'created',
            'to_status' => $ticket->status,
            'actor'     => $this->actor(),
        ]);

        if ($ownerId || $assignees || $groupId) {
            $this->assigner->assign($ticket, $ownerId ? (int) $ownerId : null, $assignees, $groupId ? (int) $groupId : null, null, $this->actor());
        }

        return redirect()->route('tickets.show', $ticket->id)
            ->with('status', "Ticket {$ticket->number} created.");
    }

    public function show(int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())
            ->with(['owner', 'group', 'assignees', 'subscriber', 'franchise', 'creator',
                    'events.fromStaff', 'events.toStaff'])
            ->findOrFail($id);

        return view('tickets.show', [
            'ticket' => $ticket,
            'staff'  => $this->assignableStaff(),
            'groups' => $this->groups(),
        ]);
    }

    public function edit(int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())->with('assignees')->findOrFail($id);

        return view('tickets.edit', [
            'ticket'      => $ticket,
            'staff'       => $this->assignableStaff(),
            'groups'      => $this->groups(),
            'subscribers' => $this->subscribers(),
            'franchises'  => $this->franchises(),
            'selectedAssignees' => $ticket->assignees->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $this->validateData($request, $ticket->id);

        $assignees = $data['assignee_ids'] ?? [];
        $ownerId   = $data['assigned_staff_id'] ?? null;
        $groupId   = $data['staff_group_id'] ?? null;
        unset($data['assignee_ids'], $data['assigned_staff_id'], $data['staff_group_id']);

        $previousStatus = $ticket->status;

        // Resolution / closure timestamps follow the status.
        $data = $this->applyStatusTimestamps($data, $ticket);

        $ticket->update($data);

        if ($previousStatus !== $ticket->status) {
            TicketEvent::create([
                'tenant_id'   => $ticket->tenant_id,
                'ticket_id'   => $ticket->id,
                'type'        => in_array($ticket->status, ['resolved', 'closed'], true) ? 'resolved' : 'status',
                'from_status' => $previousStatus,
                'to_status'   => $ticket->status,
                'actor'       => $this->actor(),
            ]);
        }

        $this->assigner->assign(
            $ticket,
            $ownerId ? (int) $ownerId : null,
            $assignees,
            $groupId ? (int) $groupId : null,
            null,
            $this->actor(),
        );

        return redirect()->route('tickets.show', $ticket->id)
            ->with('status', "Ticket {$ticket->number} updated.");
    }

    /**
     * Assign / reassign from the ticket detail page.
     *
     * One endpoint covers all three intents the user asked for:
     *  - single owner            → `assigned_staff_id`
     *  - several staff           → `assignee_ids[]`
     *  - a whole team            → `staff_group_id`
     * Reassignment is just a different `assigned_staff_id`; the trail records
     * from → to automatically.
     */
    public function assign(Request $request, int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'assigned_staff_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'assignee_ids'   => 'nullable|array',
            'assignee_ids.*' => [
                'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'staff_group_id' => [
                'nullable', 'integer',
                Rule::exists('staff_groups', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'note' => 'nullable|string|max:1000',
        ]);

        $previousOwner = $ticket->owner?->name;

        $ticket = $this->assigner->assign(
            $ticket,
            isset($data['assigned_staff_id']) ? (int) $data['assigned_staff_id'] : null,
            $data['assignee_ids'] ?? [],
            isset($data['staff_group_id']) ? (int) $data['staff_group_id'] : null,
            $data['note'] ?? null,
            $this->actor(),
        );

        $ticket->load('owner');
        $newOwner = $ticket->owner?->name;

        $message = match (true) {
            $newOwner === null => "Ticket {$ticket->number} unassigned.",
            $previousOwner === null => "Ticket {$ticket->number} assigned to {$newOwner}.",
            $previousOwner !== $newOwner => "Ticket {$ticket->number} reassigned from {$previousOwner} to {$newOwner}.",
            default => "Ticket {$ticket->number} assignees updated.",
        };

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('tickets.show', $ticket->id)->with('status', $message);
    }

    /** Reassign to a single new owner, keeping the existing collaborators. */
    public function reassign(Request $request, int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'assigned_staff_id' => [
                'required', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
                // Reassigning to the current owner is a no-op, not an action.
                Rule::notIn([$ticket->assigned_staff_id]),
            ],
            'note' => 'nullable|string|max:1000',
        ], [
            'assigned_staff_id.not_in' => 'That staff member already owns this ticket.',
        ]);

        $from = $ticket->owner?->name ?? 'unassigned';

        $ticket = $this->assigner->reassign(
            $ticket,
            (int) $data['assigned_staff_id'],
            $data['note'] ?? null,
            $this->actor(),
        );
        $ticket->load('owner');

        $message = "Ticket {$ticket->number} reassigned from {$from} to " . ($ticket->owner?->name ?? '—') . '.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('tickets.show', $ticket->id)->with('status', $message);
    }

    /** Drop one collaborator (never the owner — see TicketAssigner). */
    public function removeAssignee(Request $request, int $ticket, int $staff)
    {
        $model = Ticket::where('tenant_id', tenant_id())->findOrFail($ticket);

        if ((int) $model->assigned_staff_id === $staff) {
            $message = 'That staff member owns this ticket — reassign it instead of removing them.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('tickets.show', $model->id)->withErrors(['ticket' => $message]);
        }

        $this->assigner->removeCollaborator($model, $staff);

        return redirect()->route('tickets.show', $model->id)
            ->with('status', 'Assignee removed from the ticket.');
    }

    /** Append a comment to the activity trail. */
    public function comment(Request $request, int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate(['note' => 'required|string|max:2000']);

        TicketEvent::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'type'      => 'comment',
            'note'      => $data['note'],
            'actor'     => $this->actor(),
        ]);

        return redirect()->route('tickets.show', $ticket->id)->with('status', 'Comment added.');
    }

    public function destroy(Request $request, int $id)
    {
        $ticket = Ticket::where('tenant_id', tenant_id())->findOrFail($id);
        $number = $ticket->number;

        $ticket->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Ticket {$number} deleted."]);
        }

        return redirect()->route('tickets.index')->with('status', "Ticket {$number} deleted.");
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('tickets', 'number')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id()))
                    ->ignore($ignoreId),
            ],
            'subject'     => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'category'    => 'required|in:' . implode(',', array_keys(Ticket::CATEGORIES)),
            'priority'    => 'required|in:' . implode(',', array_keys(Ticket::PRIORITIES)),
            'status'      => 'required|in:' . implode(',', array_keys(Ticket::STATUSES)),
            'source'      => 'required|in:' . implode(',', array_keys(Ticket::SOURCES)),

            'subscriber_id' => [
                'nullable', 'integer',
                Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'franchise_id' => [
                'nullable', 'integer',
                Rule::exists('franchises', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],

            'assigned_staff_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'assignee_ids'   => 'nullable|array',
            'assignee_ids.*' => [
                'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'staff_group_id' => [
                'nullable', 'integer',
                Rule::exists('staff_groups', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],

            'due_at'        => 'nullable|date',
            'resolution'    => 'nullable|string|max:5000',
            'contact_name'  => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:500',
        ]);
    }

    /** Stamp resolved_at / closed_at when the status crosses into them. */
    private function applyStatusTimestamps(array $data, Ticket $ticket): array
    {
        $status = $data['status'] ?? $ticket->status;

        $data['resolved_at'] = in_array($status, ['resolved', 'closed'], true)
            ? ($ticket->resolved_at ?? now())
            : null;

        $data['closed_at'] = in_array($status, ['closed', 'cancelled'], true)
            ? ($ticket->closed_at ?? now())
            : null;

        return $data;
    }

    /** Who is performing the action, for the audit trail. */
    private function actor(): ?string
    {
        return auth()->user()->name ?? null;
    }

    private function assignableStaff()
    {
        return Staff::where('tenant_id', tenant_id())
            ->whereIn('status', ['active', 'on_leave'])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'designation', 'role']);
    }

    private function groups()
    {
        return StaffGroup::where('tenant_id', tenant_id())
            ->where('is_active', true)
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    private function subscribers()
    {
        return Subscriber::where('tenant_id', tenant_id())
            ->orderBy('username')
            ->limit(500)
            ->get(['id', 'username', 'first_name', 'last_name', 'mobile']);
    }

    private function franchises()
    {
        return Franchise::where('tenant_id', tenant_id())->orderBy('name')->get(['id', 'code', 'name']);
    }

    /** Header tiles. */
    private function summary(): array
    {
        $base = fn () => Ticket::where('tenant_id', tenant_id());

        return [
            'open'       => $base()->whereNotIn('status', Ticket::CLOSED_STATUSES)->count(),
            'unassigned' => $base()->whereNull('assigned_staff_id')
                ->whereNotIn('status', Ticket::CLOSED_STATUSES)->count(),
            'urgent'     => $base()->where('priority', 'urgent')
                ->whereNotIn('status', Ticket::CLOSED_STATUSES)->count(),
            'overdue'    => $base()->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'resolved'   => $base()->whereIn('status', ['resolved', 'closed'])->count(),
        ];
    }
}
