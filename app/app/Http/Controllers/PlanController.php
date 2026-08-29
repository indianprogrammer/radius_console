<?php

namespace App\Http\Controllers;

use App\Src\Domain\Plan;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\PlanRepository;
use App\Src\Ports\TaxRateRepository;
use Illuminate\Http\Request;

/**
 * Billing Plans — financial details only (name, price, cycle). The network
 * behaviour is delegated to a RADIUS-synced BandwidthProfile selected via
 * `bandwidth_profile_id`. Plans are local-only; no RADIUS sync happens here.
 */
final class PlanController extends Controller
{
    public function index(PlanRepository $plans, BandwidthProfileRepository $profiles, TaxRateRepository $taxes)
    {
        return view('plans.index', [
            'plans' => $plans->listByTenant(tenant_id()),
            'profiles' => $profiles->listByCompany((int) tenant_id()),
            'taxes' => $taxes->listByTenant(tenant_id()),
        ]);
    }

    public function create(BandwidthProfileRepository $profiles, TaxRateRepository $taxes)
    {
        return view('plans.create', [
            'profiles' => $profiles->listByCompany((int) tenant_id()),
            'taxes' => $taxes->listByTenant(tenant_id()),
        ]);
    }

    public function store(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles, TaxRateRepository $taxes)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'required|numeric|min:0',
            'cycle' => 'required|string|in:monthly,quarterly,yearly',
            'bandwidth_profile_id' => 'nullable|integer|exists:bandwidth_profiles,id',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_rate_id' => 'nullable|integer|exists:tax_rates,id',
        ]);

        if ($data['bandwidth_profile_id'] !== null) {
            $bp = $profiles->find((int) $data['bandwidth_profile_id']);
            if ($bp === null || $bp->companyId !== (int) tenant_id()) {
                return back()->withInput()->withErrors(['bandwidth_profile_id' => 'Unknown bandwidth profile.']);
            }
        }
        if ($data['tax_rate_id'] !== null) {
            $tr = $taxes->find((int) $data['tax_rate_id']);
            if ($tr === null || $tr->tenantId !== tenant_id()) {
                return back()->withInput()->withErrors(['tax_rate_id' => 'Unknown tax rate.']);
            }
        }

        $entity = new Plan(
            id: null,
            tenantId: tenant_id(),
            name: $data['name'],
            price: (float) $data['price'],
            cycle: $data['cycle'],
            bandwidthProfileId: $data['bandwidth_profile_id'] ? (int) $data['bandwidth_profile_id'] : null,
            taxRate: (float) ($data['tax_rate'] ?? 0),
            taxRateId: $data['tax_rate_id'] ? (int) $data['tax_rate_id'] : null,
        );
        $plans->save($entity);

        return redirect()->route('plans.index')->with('status', 'Plan created.');
    }

    public function edit(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles, TaxRateRepository $taxes, int $id)
    {
        $plan = $plans->find($id);
        if ($plan === null) {
            abort(404);
        }
        return view('plans.edit', [
            'plan' => $plan,
            'profiles' => $profiles->listByCompany((int) tenant_id()),
            'taxes' => $taxes->listByTenant(tenant_id()),
        ]);
    }

    public function update(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles, TaxRateRepository $taxes, int $id)
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
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_rate_id' => 'nullable|integer|exists:tax_rates,id',
        ]);

        if ($data['bandwidth_profile_id'] !== null) {
            $bp = $profiles->find((int) $data['bandwidth_profile_id']);
            if ($bp === null || $bp->companyId !== (int) tenant_id()) {
                return back()->withInput()->withErrors(['bandwidth_profile_id' => 'Unknown bandwidth profile.']);
            }
        }
        if ($data['tax_rate_id'] !== null) {
            $tr = $taxes->find((int) $data['tax_rate_id']);
            if ($tr === null || $tr->tenantId !== tenant_id()) {
                return back()->withInput()->withErrors(['tax_rate_id' => 'Unknown tax rate.']);
            }
        }

        $entity = new Plan(
            id: $plan->id,
            tenantId: $plan->tenantId,
            name: $data['name'],
            price: (float) $data['price'],
            cycle: $data['cycle'],
            bandwidthProfileId: $data['bandwidth_profile_id'] ? (int) $data['bandwidth_profile_id'] : null,
            taxRate: (float) ($data['tax_rate'] ?? 0),
            taxRateId: $data['tax_rate_id'] ? (int) $data['tax_rate_id'] : null,
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
