<?php

namespace App\Http\Controllers;

use App\Src\Domain\Plan;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\PlanRepository;
use Illuminate\Http\Request;

/**
 * Billing Plans — financial details only (name, price, cycle). The network
 * behaviour is delegated to a RADIUS-synced BandwidthProfile selected via
 * `bandwidth_profile_id`. Plans are local-only; no RADIUS sync happens here.
 */
final class PlanController extends Controller
{
    public function index(PlanRepository $plans, BandwidthProfileRepository $profiles)
    {
        return view('plans.index', [
            'plans' => $plans->listByTenant(tenant_id()),
            'profiles' => $profiles->listByCompany((int) tenant_id()),
        ]);
    }

    public function create(BandwidthProfileRepository $profiles)
    {
        return view('plans.create', ['profiles' => $profiles->listByCompany((int) tenant_id())]);
    }

    public function store(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0',
            'cycle' => 'required|string|in:monthly,quarterly,yearly',
            'bandwidth_profile_id' => 'nullable|integer|exists:bandwidth_profiles,id',
        ]);

        if ($data['bandwidth_profile_id'] !== null) {
            $bp = $profiles->find((int) $data['bandwidth_profile_id']);
            if ($bp === null || $bp->companyId !== (int) tenant_id()) {
                return back()->withInput()->withErrors(['bandwidth_profile_id' => 'Unknown bandwidth profile.']);
            }
        }

        $entity = new Plan(
            id: null,
            tenantId: tenant_id(),
            name: $data['name'],
            price: (float) $data['price'],
            cycle: $data['cycle'],
            bandwidthProfileId: $data['bandwidth_profile_id'] ? (int) $data['bandwidth_profile_id'] : null,
        );
        $plans->save($entity);

        return redirect()->route('plans.index')->with('status', 'Plan created.');
    }

    public function edit(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles, int $id)
    {
        $plan = $plans->find($id);
        if ($plan === null) {
            abort(404);
        }
        return view('plans.edit', [
            'plan' => $plan,
            'profiles' => $profiles->listByCompany((int) tenant_id()),
        ]);
    }

    public function update(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles, int $id)
    {
        $plan = $plans->find($id);
        if ($plan === null) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0',
            'cycle' => 'required|string|in:monthly,quarterly,yearly',
            'bandwidth_profile_id' => 'nullable|integer|exists:bandwidth_profiles,id',
        ]);

        if ($data['bandwidth_profile_id'] !== null) {
            $bp = $profiles->find((int) $data['bandwidth_profile_id']);
            if ($bp === null || $bp->companyId !== (int) tenant_id()) {
                return back()->withInput()->withErrors(['bandwidth_profile_id' => 'Unknown bandwidth profile.']);
            }
        }

        $entity = new Plan(
            id: $plan->id,
            tenantId: $plan->tenantId,
            name: $data['name'],
            price: (float) $data['price'],
            cycle: $data['cycle'],
            bandwidthProfileId: $data['bandwidth_profile_id'] ? (int) $data['bandwidth_profile_id'] : null,
        );
        $plans->save($entity);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Plan updated.']);
        }
        return redirect()->route('plans.index')->with('status', 'Plan updated.');
    }

    public function destroy(Request $request, PlanRepository $plans, int $id)
    {
        $plan = $plans->find($id);
        if ($plan === null) {
            abort(404);
        }
        $plans->delete($plan->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Plan deleted.']);
        }
        return redirect()->route('plans.index')->with('status', 'Plan deleted.');
    }
}
