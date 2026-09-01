<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff groups / teams — Staff & HR.
 *
 * A group exists so a ticket can be handed to a whole team in one action
 * (`TicketAssigner` expands the members). Deleting a group is safe: tickets
 * keep the assignees that were expanded at the time, and `staff_group_id`
 * is nulled by the FK.
 */
final class StaffGroupController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $groups = StaffGroup::query()
            ->where('tenant_id', tenant_id())
            ->withCount('members')
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('staff-groups.index', [
            'groups' => $groups,
            'search' => $request->query('q'),
        ]);
    }

    public function create()
    {
        return view('staff-groups.create', [
            'group'         => new StaffGroup(['is_active' => true]),
            'staff'         => $this->assignableStaff(),
            'selectedStaff' => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);

        $data['tenant_id'] = tenant_id();
        $data['is_active'] = $request->boolean('is_active');

        $group = StaffGroup::create($data);
        $this->syncMembers($group, $memberIds);

        return redirect()->route('staff-groups.index')
            ->with('status', "Group {$group->name} created.");
    }

    public function edit(int $id)
    {
        $group = StaffGroup::where('tenant_id', tenant_id())->with('members')->findOrFail($id);

        return view('staff-groups.edit', [
            'group'         => $group,
            'staff'         => $this->assignableStaff(),
            'selectedStaff' => $group->members->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $group = StaffGroup::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $this->validateData($request, $group->id);

        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);

        $data['is_active'] = $request->boolean('is_active');

        $group->update($data);
        $this->syncMembers($group, $memberIds);

        return redirect()->route('staff-groups.index')
            ->with('status', "Group {$group->name} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $group = StaffGroup::where('tenant_id', tenant_id())->findOrFail($id);
        $name = $group->name;

        // Members are only a pivot; cascade removes them, tickets keep theirs.
        $group->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Group {$name} deleted."]);
        }

        return redirect()->route('staff-groups.index')->with('status', "Group {$name} deleted.");
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('staff_groups', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id()))
                    ->ignore($ignoreId),
            ],
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'member_ids'  => 'nullable|array',
            'member_ids.*' => [
                'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
        ]);
    }

    /** The pivot carries `tenant_id` (NOT NULL under RLS). */
    private function syncMembers(StaffGroup $group, array $memberIds): void
    {
        $pivot = [];
        foreach (array_unique(array_map('intval', $memberIds)) as $staffId) {
            $pivot[$staffId] = ['tenant_id' => $group->tenant_id];
        }

        $group->members()->sync($pivot);
    }

    private function assignableStaff()
    {
        return Staff::where('tenant_id', tenant_id())
            ->whereIn('status', ['active', 'on_leave'])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'designation']);
    }
}
