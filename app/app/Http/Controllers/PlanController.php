<?php

namespace App\Http\Controllers;

use App\Src\Domain\Plan;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\PlanRepository;
use App\Src\Ports\TaxRateRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Billing Plans — financial details only (name, price, cycle). The network
 * behaviour is delegated to a RADIUS-synced BandwidthProfile selected via
 * `bandwidth_profile_id`. Plans are local-only; no RADIUS sync happens here.
 */
final class PlanController extends Controller
{
    public function index(Request $request, PlanRepository $plans, BandwidthProfileRepository $profiles, TaxRateRepository $taxes)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $plans->listByTenant(tenant_id());

        // Slice + wrap in a LengthAwarePaginator so the view renders real
        // pagination links via {{ $plans->links() }} (matches NAS list).
        $items = collect($list);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $request->query()]
        );

        return view('plans.index', [
            'plans' => $paginator,
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
            'data_limit_gb' => 'nullable|integer|min:0',
            'tax_rate_ids' => 'nullable|array',
            'tax_rate_ids.*' => 'integer|exists:tax_rates,id',
        ]);

        if ($data['bandwidth_profile_id'] !== null) {
            $bp = $profiles->find((int) $data['bandwidth_profile_id']);
            if ($bp === null || $bp->companyId !== (int) tenant_id()) {
                return back()->withInput()->withErrors(['bandwidth_profile_id' => 'Unknown bandwidth profile.']);
            }
        }
        $selectedTaxes = $this->resolveTaxes($taxes, $data['tax_rate_ids'] ?? []);

        $entity = new Plan(
            id: null,
            tenantId: tenant_id(),
            name: $data['name'],
            price: (float) $data['price'],
            cycle: $data['cycle'],
            bandwidthProfileId: $data['bandwidth_profile_id'] ? (int) $data['bandwidth_profile_id'] : null,
            dataLimitGb: $data['data_limit_gb'] !== null && $data['data_limit_gb'] !== '' ? (int) $data['data_limit_gb'] : null,
            taxRates: $selectedTaxes,
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
            'data_limit_gb' => 'nullable|integer|min:0',
            'tax_rate_ids' => 'nullable|array',
            'tax_rate_ids.*' => 'integer|exists:tax_rates,id',
        ]);

        if ($data['bandwidth_profile_id'] !== null) {
            $bp = $profiles->find((int) $data['bandwidth_profile_id']);
            if ($bp === null || $bp->companyId !== (int) tenant_id()) {
                return back()->withInput()->withErrors(['bandwidth_profile_id' => 'Unknown bandwidth profile.']);
            }
        }
        $selectedTaxes = $this->resolveTaxes($taxes, $data['tax_rate_ids'] ?? []);

        $entity = new Plan(
            id: $plan->id,
            tenantId: $plan->tenantId,
            name: $data['name'],
            price: (float) $data['price'],
            cycle: $data['cycle'],
            bandwidthProfileId: $data['bandwidth_profile_id'] ? (int) $data['bandwidth_profile_id'] : null,
            dataLimitGb: $data['data_limit_gb'] !== null && $data['data_limit_gb'] !== '' ? (int) $data['data_limit_gb'] : null,
            taxRates: $selectedTaxes,
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

    /**
     * Resolve submitted tax ids into domain TaxRate entities, scoped to the
     * current tenant. Returns [] when none selected (a plan may have no tax).
     */
    private function resolveTaxes(TaxRateRepository $taxes, array $ids): array
    {
        $out = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $tr = $taxes->find($id);
            if ($tr !== null && $tr->tenantId === tenant_id()) {
                $out[] = $tr;
            }
        }
        return $out;
    }
}
