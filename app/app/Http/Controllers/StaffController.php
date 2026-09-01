<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use App\Models\Staff;
use App\Models\StaffGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Staff / employee master — Staff & HR.
 *
 * Every query is tenant scoped. Deletion is refused while the employee still
 * owns tickets or has payroll history, because both are records that must stay
 * attributable; mark them `resigned` instead.
 */
final class StaffController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $staff = Staff::query()
            ->where('tenant_id', tenant_id())
            ->with(['franchise', 'manager'])
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department', $d))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('designation', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('staff.index', [
            'staff'      => $staff,
            'search'     => $request->query('q'),
            'role'       => $request->query('role'),
            'status'     => $request->query('status'),
            'department' => $request->query('department'),
            'totals'     => $this->summary(),
        ]);
    }

    public function create()
    {
        return view('staff.create', [
            'member' => new Staff([
                'code'            => Staff::nextCode(tenant_id()),
                'role'            => 'technician',
                'employment_type' => 'full_time',
                'status'          => 'active',
                'date_of_joining' => now()->toDateString(),
            ]),
            'managers'   => $this->managers(),
            'franchises' => $this->franchises(),
            'groups'     => $this->groups(),
            'selectedGroups' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $groupIds = $data['group_ids'] ?? [];
        unset($data['group_ids']);

        $data['tenant_id'] = tenant_id();
        // `code` may be absent (nullable) or nulled by ConvertEmptyStringsToNull.
        $data['code'] = ($data['code'] ?? null) ?: Staff::nextCode(tenant_id());

        $member = Staff::create($data);
        $this->syncGroups($member, $groupIds);

        return redirect()->route('staff.index')
            ->with('status', "Staff {$member->code} — {$member->name} created.");
    }

    public function show(int $id)
    {
        $member = Staff::where('tenant_id', tenant_id())
            ->with(['franchise', 'manager', 'groups'])
            ->findOrFail($id);

        return view('staff.show', [
            'member'   => $member,
            'payslips' => $member->payslips()->orderByDesc('period_month')->limit(12)->get(),
            'tickets'  => $member->ownedTickets()->orderByDesc('created_at')->limit(10)->get(),
            'recent'   => $member->attendance()->orderByDesc('work_date')->limit(15)->get(),
        ]);
    }

    public function edit(int $id)
    {
        $member = Staff::where('tenant_id', tenant_id())->with('groups')->findOrFail($id);

        return view('staff.edit', [
            'member'         => $member,
            'managers'       => $this->managers($member->id),
            'franchises'     => $this->franchises(),
            'groups'         => $this->groups(),
            'selectedGroups' => $member->groups->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $member = Staff::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $this->validateData($request, $member->id);

        $groupIds = $data['group_ids'] ?? [];
        unset($data['group_ids']);

        $member->update($data);
        $this->syncGroups($member, $groupIds);

        return redirect()->route('staff.index')
            ->with('status', "Staff {$member->code} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $member = Staff::where('tenant_id', tenant_id())->findOrFail($id);
        $code = $member->code;

        // Tickets and payslips must remain attributable to a real employee.
        $blockers = [];
        if ($member->ownedTickets()->exists()) {
            $blockers[] = 'assigned tickets';
        }
        if ($member->payslips()->exists()) {
            $blockers[] = 'payroll history';
        }
        if ($member->reports()->exists()) {
            $blockers[] = 'direct reports';
        }

        if ($blockers !== []) {
            $message = "Staff {$code} has " . implode(' and ', $blockers)
                . ' — set the status to Resigned instead of deleting.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('staff.index')->withErrors(['staff' => $message]);
        }

        $member->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Staff {$code} deleted."]);
        }

        return redirect()->route('staff.index')->with('status', "Staff {$code} deleted.");
    }

    /** @param int|null $ignoreId Employee being edited (excluded from unique + manager list). */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('staff', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id()))
                    ->ignore($ignoreId),
            ],
            'name'            => 'required|string|max:150',
            'role'            => 'required|in:' . implode(',', array_keys(Staff::ROLES)),
            'designation'     => 'nullable|string|max:100',
            'department'      => 'nullable|string|max:100',
            'employment_type' => 'required|in:' . implode(',', array_keys(Staff::EMPLOYMENT_TYPES)),
            'status'          => 'required|in:' . implode(',', array_keys(Staff::STATUSES)),

            'franchise_id' => [
                'nullable', 'integer',
                Rule::exists('franchises', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'reports_to_id' => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
                // An employee cannot report to themselves.
                Rule::notIn($ignoreId === null ? [] : [$ignoreId]),
            ],

            'email'             => 'nullable|email|max:150',
            'phone'             => 'nullable|string|max:20',
            'emergency_contact' => 'nullable|string|max:20',

            'date_of_birth'   => 'nullable|date',
            'date_of_joining' => 'nullable|date',
            'date_of_leaving' => 'nullable|date|after_or_equal:date_of_joining',

            'address' => 'nullable|string|max:500',
            'city'    => 'nullable|string|max:100',
            'state'   => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:12',

            'pan_number'          => 'nullable|string|max:20',
            'aadhaar_number'      => 'nullable|string|max:20',
            'bank_account_name'   => 'nullable|string|max:150',
            'bank_account_number' => 'nullable|string|max:40',
            'bank_ifsc'           => 'nullable|string|max:20',

            'basic_salary'           => 'required|numeric|min:0',
            'hra'                    => 'nullable|numeric|min:0',
            'other_allowances'       => 'nullable|numeric|min:0',
            'pf_percent'             => 'nullable|numeric|min:0|max:100',
            'esi_percent'            => 'nullable|numeric|min:0|max:100',
            'professional_tax'       => 'nullable|numeric|min:0',
            'overtime_rate_per_hour' => 'nullable|numeric|min:0',

            'group_ids'   => 'nullable|array',
            'group_ids.*' => [
                'integer',
                Rule::exists('staff_groups', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],

            'notes' => 'nullable|string|max:1000',
        ]);
    }

    /** The pivot carries `tenant_id` (NOT NULL under RLS), so stamp it on sync. */
    private function syncGroups(Staff $member, array $groupIds): void
    {
        $pivot = [];
        foreach (array_unique(array_map('intval', $groupIds)) as $groupId) {
            $pivot[$groupId] = ['tenant_id' => $member->tenant_id];
        }

        $member->groups()->sync($pivot);
    }

    private function managers(?int $excludeId = null)
    {
        return Staff::where('tenant_id', tenant_id())
            ->when($excludeId, fn ($q, $id) => $q->whereKeyNot($id))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'designation']);
    }

    private function franchises()
    {
        return Franchise::where('tenant_id', tenant_id())->orderBy('name')->get(['id', 'code', 'name']);
    }

    private function groups()
    {
        return StaffGroup::where('tenant_id', tenant_id())->orderBy('name')->get(['id', 'name']);
    }

    /** Header tiles. */
    private function summary(): array
    {
        $base = fn () => Staff::where('tenant_id', tenant_id());

        return [
            'total'    => $base()->count(),
            'active'   => $base()->where('status', 'active')->count(),
            'on_leave' => $base()->where('status', 'on_leave')->count(),
            'payroll'  => round((float) $base()->whereIn('status', ['active', 'on_leave'])
                ->sum(DB::raw('basic_salary + hra + other_allowances')), 2),
        ];
    }
}
