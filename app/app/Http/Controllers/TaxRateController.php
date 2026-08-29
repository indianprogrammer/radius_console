<?php

namespace App\Http\Controllers;

use App\Src\Domain\TaxRate;
use App\Src\Ports\TaxRateRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Tax Rates — managed under Billing & Invoices. Tenants create reusable taxes
 * (e.g. "VAT 18%") and attach them to billing plans. Local-only (no RADIUS
 * sync — taxes are a billing concern, SRD §7.2).
 */
final class TaxRateController extends Controller
{
    public function index(Request $request, TaxRateRepository $taxes)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $taxes->listByTenant(tenant_id());

        $items = collect($list);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $request->query()]
        );

        return view('tax-rates.index', [
            'taxes' => $paginator,
        ]);
    }

    public function create()
    {
        return view('tax-rates.create');
    }

    public function store(Request $request, TaxRateRepository $taxes)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|string|in:percentage,fixed',
        ]);

        $taxes->save(new TaxRate(
            id: null,
            tenantId: tenant_id(),
            name: $data['name'],
            rate: (float) $data['rate'],
            type: $data['type'],
        ));

        return redirect()->route('tax-rates.index')->with('status', 'Tax rate created.');
    }

    public function edit(TaxRateRepository $taxes, int $id)
    {
        $tax = $taxes->find($id);
        if ($tax === null) {
            abort(404);
        }
        return view('tax-rates.edit', ['tax' => $tax]);
    }

    public function update(Request $request, TaxRateRepository $taxes, int $id)
    {
        $tax = $taxes->find($id);
        if ($tax === null) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|string|in:percentage,fixed',
        ]);

        $taxes->save(new TaxRate(
            id: $tax->id,
            tenantId: $tax->tenantId,
            name: $data['name'],
            rate: (float) $data['rate'],
            type: $data['type'],
        ));

        return redirect()->route('tax-rates.index')->with('status', 'Tax rate updated.');
    }

    public function destroy(Request $request, TaxRateRepository $taxes, int $id)
    {
        $tax = $taxes->find($id);
        if ($tax === null) {
            abort(404);
        }
        $taxes->delete($id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Tax rate deleted.']);
        }
        return redirect()->route('tax-rates.index')->with('status', 'Tax rate deleted.');
    }
}
