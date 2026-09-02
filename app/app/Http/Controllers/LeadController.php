<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\Subscriber;
use App\Services\LeadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sales pipeline — the "Sales" menu group.
 *
 * Every pipeline move goes through `LeadService` so the stage, the timestamps,
 * the follow-up queue and the `lead_activities` trail can never drift apart.
 * The controller only validates and reports.
 */
final class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $leads = Lead::query()
            ->where('tenant_id', tenant_id())
            ->with(['owner', 'plan', 'quote', 'subscriber'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('source'), fn ($q, $s) => $q->where('source', $s))
            ->when($request->query('rating'), fn ($q, $r) => $q->where('rating', $r))
            ->when($request->query('staff_id'), fn ($q, $id) => $q->where('assigned_staff_id', $id))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('assigned_staff_id'))
            // The Follow-ups queue: open leads whose follow-up date has passed.
            ->when($request->boolean('due'), fn ($q) => $q
                ->whereNotIn('status', Lead::CLOSED_STATUSES)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now()))
            ->when($request->boolean('open'), fn ($q) => $q->whereNotIn('status', Lead::CLOSED_STATUSES))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            // Hot first, then the oldest untouched lead — that is the call list.
            ->orderByRaw("CASE rating WHEN 'hot' THEN 1 WHEN 'warm' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('leads.index', [
            'leads'      => $leads,
            'search'     => $request->query('q'),
            'status'     => $request->query('status'),
            'source'     => $request->query('source'),
            'rating'     => $request->query('rating'),
            'staffId'    => $request->query('staff_id'),
            'unassigned' => $request->boolean('unassigned'),
            'due'        => $request->boolean('due'),
            'openOnly'   => $request->boolean('open'),
            'staff'      => $this->salesStaff(),
            'totals'     => $this->summary(),
            'funnel'     => $this->funnel(),
        ]);
    }

    /**
     * Kanban board of the OPEN pipeline — the "what is still in play" view.
     *
     * Deliberately unpaginated and closed-lead-free: a board is read as a whole,
     * and a won/lost lead is history that would only widen the columns. Cards
     * carry their own stage `<select>` beside the drag handle, because native
     * HTML5 drag-and-drop is not reachable by keyboard.
     */
    public function board(Request $request)
    {
        $leads = Lead::query()
            ->where('tenant_id', tenant_id())
            ->whereNotIn('status', Lead::CLOSED_STATUSES)
            ->with(['owner', 'plan', 'quote'])
            ->when($request->query('rating'), fn ($q, $r) => $q->where('rating', $r))
            ->when($request->query('staff_id'), fn ($q, $id) => $q->where('assigned_staff_id', $id))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('assigned_staff_id'))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            // Within a column: overdue follow-ups first, then hot leads — the
            // top of each column is the next call to make.
            ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up_at')
            ->orderByRaw("CASE rating WHEN 'hot' THEN 1 WHEN 'warm' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->get();

        // One column per open stage, always all of them: an empty column is
        // information (nothing is being negotiated), not something to hide.
        $columns = [];
        foreach (Lead::ORDERED_STAGES as $stage) {
            $inStage = $leads->where('status', $stage)->values();

            $columns[$stage] = [
                'label' => Lead::STATUSES[$stage],
                'leads' => $inStage,
                'count' => $inStage->count(),
                'value' => round((float) $inStage->sum('estimated_value'), 2),
            ];
        }

        return view('leads.board', [
            'columns'    => $columns,
            'search'     => $request->query('q'),
            'rating'     => $request->query('rating'),
            'staffId'    => $request->query('staff_id'),
            'unassigned' => $request->boolean('unassigned'),
            'staff'      => $this->salesStaff(),
            'totals'     => $this->summary(),
        ]);
    }

    /**
     * Move a lead to another OPEN stage — the board's drop / select action.
     *
     * Won and lost are rejected here on purpose: closing a deal needs its own
     * input (a subscriber link, or a reason for the funnel), so it goes through
     * win()/lose() rather than a drag that silently discards that context.
     */
    public function stage(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', Lead::ORDERED_STAGES),
            'note'   => 'nullable|string|max:1000',
        ], [], ['status' => 'stage']);

        $lead = $this->leads->changeStatus($lead, $data['status'], $data['note'] ?? null, $this->actor());

        $message = "Lead {$lead->number} moved to {$lead->statusLabel()}.";

        // The board moves cards over fetch(); a redirect would be replayed as a
        // POST to the board URL, so answer ajax callers with JSON.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'status'  => $lead->status,
            ]);
        }

        return redirect()->route('leads.board')->with('status', $message);
    }

    public function create()
    {
        return view('leads.create', [
            'lead' => new Lead([
                'number' => Lead::nextNumber(tenant_id()),
                'source' => 'phone',
                'status' => 'new',
                'rating' => 'warm',
                'estimated_value' => 0,
            ]),
            'staff'       => $this->salesStaff(),
            'plans'       => $this->plans(),
            'franchises'  => $this->franchises(),
            'subscribers' => $this->subscribers(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['tenant_id'] = tenant_id();
        $data['number']    = ($data['number'] ?? null) ?: Lead::nextNumber(tenant_id());

        $lead = Lead::create($data);

        LeadActivity::create([
            'tenant_id'   => $lead->tenant_id,
            'lead_id'     => $lead->id,
            'type'        => 'created',
            'to_status'   => $lead->status,
            'to_staff_id' => $lead->assigned_staff_id,
            'occurred_at' => now(),
            'actor'       => $this->actor(),
        ]);

        return redirect()->route('leads.show', $lead->id)
            ->with('status', "Lead {$lead->number} created.");
    }

    public function show(int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())
            ->with(['owner', 'plan', 'franchise', 'quote', 'subscriber',
                    'activities.fromStaff', 'activities.toStaff'])
            ->findOrFail($id);

        return view('leads.show', [
            'lead'        => $lead,
            'staff'       => $this->salesStaff(),
            'subscribers' => $this->subscribers(),
        ]);
    }

    public function edit(int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        return view('leads.edit', [
            'lead'        => $lead,
            'staff'       => $this->salesStaff(),
            'plans'       => $this->plans(),
            'franchises'  => $this->franchises(),
            'subscribers' => $this->subscribers(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $this->validateData($request, $lead->id);

        $previousStatus = $lead->status;
        $previousOwner  = $lead->assigned_staff_id ? (int) $lead->assigned_staff_id : null;
        $newStatus      = $data['status'];
        // The stage transition is applied by LeadService, which owns the
        // timestamps and the trail — a plain update() would skip both.
        unset($data['status']);

        $lead->update($data);

        $newOwner = $lead->assigned_staff_id ? (int) $lead->assigned_staff_id : null;
        if ($previousOwner !== $newOwner) {
            LeadActivity::create([
                'tenant_id'     => $lead->tenant_id,
                'lead_id'       => $lead->id,
                'type'          => 'assigned',
                'from_staff_id' => $previousOwner,
                'to_staff_id'   => $newOwner,
                'occurred_at'   => now(),
                'actor'         => $this->actor(),
            ]);
        }

        if ($newStatus !== $previousStatus) {
            $lead = $this->leads->changeStatus($lead, $newStatus, null, $this->actor());
        }

        return redirect()->route('leads.show', $lead->id)
            ->with('status', "Lead {$lead->number} updated.");
    }

    /** Append a call / note / meeting to the trail. */
    public function activity(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(LeadActivity::TYPES)),
            'note' => 'nullable|string|max:2000',
        ]);

        $this->leads->logActivity($lead, $data['type'], [
            'note' => $data['note'] ?? null,
        ], $this->actor());

        return redirect()->route('leads.show', $lead->id)
            ->with('status', 'Activity recorded.');
    }

    /** Schedule the next follow-up, which puts the lead in the due queue. */
    public function followUp(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'next_follow_up_at' => 'required|date',
            'note'              => 'nullable|string|max:1000',
        ]);

        $this->leads->scheduleFollowUp(
            $lead,
            new \DateTimeImmutable($data['next_follow_up_at']),
            $data['note'] ?? null,
            $this->actor(),
        );

        return redirect()->route('leads.show', $lead->id)
            ->with('status', 'Follow-up scheduled.');
    }

    /** Raise a quotation from the lead and jump to it for editing. */
    public function quote(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->with(['plan', 'quote'])->findOrFail($id);

        try {
            $quote = $this->leads->createQuotation($lead, $this->actor());
        } catch (\RuntimeException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('leads.show', $lead->id)->withErrors(['lead' => $e->getMessage()]);
        }

        return redirect()->route('quotes.edit', ['quotation', $quote->id])
            ->with('status', "Quotation {$quote->number} raised from lead {$lead->number}. Add the line items and send it.");
    }

    /** Close the lead as won. */
    public function win(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate([
            'note'          => 'nullable|string|max:1000',
            'subscriber_id' => [
                'nullable', 'integer',
                Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
        ]);

        if (!empty($data['subscriber_id'])) {
            $lead->update(['subscriber_id' => $data['subscriber_id']]);
        }

        $lead = $this->leads->markWon($lead, $data['note'] ?? null, $this->actor());

        return redirect()->route('leads.show', $lead->id)
            ->with('status', "Lead {$lead->number} marked as won.");
    }

    /** Close the lead as lost, with a reason for the funnel analysis. */
    public function lose(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $request->validate(['lost_reason' => 'required|string|max:200']);

        $lead = $this->leads->markLost($lead, $data['lost_reason'], $this->actor());

        return redirect()->route('leads.show', $lead->id)
            ->with('status', "Lead {$lead->number} marked as lost.");
    }

    public function destroy(Request $request, int $id)
    {
        $lead = Lead::where('tenant_id', tenant_id())->findOrFail($id);
        $number = $lead->number;

        $lead->delete();

        // The index deletes over fetch(); a redirect would be replayed as a
        // DELETE against /leads (405), so answer ajax callers with JSON.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Lead {$number} deleted."]);
        }

        return redirect()->route('leads.index')->with('status', "Lead {$number} deleted.");
    }

    /**
     * @param int|null $ignoreId Lead being edited (excluded from the unique check).
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('leads', 'number')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id()))
                    ->ignore($ignoreId),
            ],
            'name'            => 'required|string|max:150',
            'company'         => 'nullable|string|max:150',
            'email'           => 'nullable|email|max:150',
            'phone'           => 'nullable|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:100',
            'pincode'         => 'nullable|string|max:12',

            'source' => 'required|in:' . implode(',', array_keys(Lead::SOURCES)),
            'status' => 'required|in:' . implode(',', array_keys(Lead::STATUSES)),
            'rating' => 'required|in:' . implode(',', array_keys(Lead::RATINGS)),

            'plan_id' => [
                'nullable', 'integer',
                Rule::exists('plans', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'estimated_value' => 'nullable|numeric|min:0',

            'assigned_staff_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'franchise_id' => [
                'nullable', 'integer',
                Rule::exists('franchises', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'subscriber_id' => [
                'nullable', 'integer',
                Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],

            'next_follow_up_at' => 'nullable|date',
            'notes'             => 'nullable|string|max:5000',
        ]);
    }

    /** Who is performing the action, for the trail. */
    private function actor(): ?string
    {
        return auth()->user()->name ?? null;
    }

    /**
     * Assignable owners. Sales staff first but not exclusively — a small ISP
     * often has the LCO manager working leads personally.
     */
    private function salesStaff()
    {
        return Staff::where('tenant_id', tenant_id())
            ->whereIn('status', ['active', 'on_leave'])
            ->orderByRaw("CASE WHEN role = 'sales' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'designation', 'role']);
    }

    private function plans()
    {
        return Plan::where('tenant_id', tenant_id())->orderBy('name')->get(['id', 'name', 'price']);
    }

    private function franchises()
    {
        return Franchise::where('tenant_id', tenant_id())->orderBy('name')->get(['id', 'code', 'name']);
    }

    private function subscribers()
    {
        return Subscriber::where('tenant_id', tenant_id())
            ->orderBy('username')
            ->limit(500)
            ->get(['id', 'username', 'first_name', 'last_name']);
    }

    /** Header tiles. */
    private function summary(): array
    {
        $base = fn () => Lead::where('tenant_id', tenant_id());

        $open = $base()->whereNotIn('status', Lead::CLOSED_STATUSES);
        $won  = $base()->where('status', 'won')->count();
        $lost = $base()->where('status', 'lost')->count();
        $closed = $won + $lost;

        return [
            'open'       => (clone $open)->count(),
            'due'        => $base()->whereNotIn('status', Lead::CLOSED_STATUSES)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now())->count(),
            'unassigned' => $base()->whereNotIn('status', Lead::CLOSED_STATUSES)
                ->whereNull('assigned_staff_id')->count(),
            'won'        => $won,
            // Open pipeline value only — won money belongs to invoices.
            'pipeline'   => (float) $base()->whereNotIn('status', Lead::CLOSED_STATUSES)
                ->sum('estimated_value'),
            // Percent of DECIDED leads that were won; open leads have no verdict
            // yet, so counting them would understate the rate all month.
            'win_rate'   => $closed > 0 ? round($won / $closed * 100, 1) : 0.0,
        ];
    }

    /** Count per open stage, for the pipeline strip on the index. */
    private function funnel(): array
    {
        $counts = Lead::where('tenant_id', tenant_id())
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $funnel = [];
        foreach (Lead::ORDERED_STAGES as $stage) {
            $funnel[$stage] = (int) ($counts[$stage] ?? 0);
        }

        return $funnel;
    }
}