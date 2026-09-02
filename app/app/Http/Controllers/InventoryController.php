<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $lowOnly = $request->boolean('low_stock');

        $items = Inventory::query()
            ->where('tenant_id', tenant_id())
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('status'), fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->when($lowOnly, fn ($q) => $q->lowStock())
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('sku', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('inventory.index', [
            'items'    => $items,
            'search'   => $request->query('q'),
            'category' => $request->query('category'),
            'status'   => $request->query('status'),
            'lowStock' => $lowOnly,
            'totals'   => $this->summary(),
        ]);
    }

    public function create()
    {
        return view('inventory.create', [
            'item' => new Inventory([
                'category'       => 'physical',
                'unit'           => 'pcs',
                'stock_quantity' => 0,
                'reorder_point'  => 0,
                'cost_price'     => 0,
                'sale_price'     => 0,
                'is_active'      => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['tenant_id'] = tenant_id();
        $data['is_active'] = $request->boolean('is_active');

        $item = Inventory::create($data);

        return redirect()->route('inventory.index')
            ->with('status', "Inventory item {$item->sku} created.");
    }

    public function edit(int $id)
    {
        $item = Inventory::where('tenant_id', tenant_id())->findOrFail($id);

        return view('inventory.edit', ['item' => $item]);
    }

    public function update(Request $request, int $id)
    {
        $item = Inventory::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $this->validateData($request, $item->id);
        $data['is_active'] = $request->boolean('is_active');

        $item->update($data);

        return redirect()->route('inventory.index')
            ->with('status', "Inventory item {$item->sku} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $item = Inventory::where('tenant_id', tenant_id())->findOrFail($id);
        $sku = $item->sku;
        $item->delete();

        // The index deletes over fetch(); a redirect would be replayed as a
        // DELETE against /inventory (405), so answer ajax callers with JSON.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Inventory item {$sku} deleted."]);
        }

        return redirect()->route('inventory.index')
            ->with('status', "Inventory item {$sku} deleted.");
    }

    /** Header cards. Counted over the whole tenant, not the current page. */
    private function summary(): array
    {
        $base = fn () => Inventory::where('tenant_id', tenant_id());

        return [
            'total'  => $base()->count(),
            'active' => $base()->where('is_active', true)->count(),
            'low'    => $base()->lowStock()->count(),
            'value'  => (float) $base()->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) AS v')->value('v'),
        ];
    }

    /**
     * @param int|null $ignoreId Item being edited (excluded from the unique check).
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'sku' => [
                'required', 'string', 'max:100',
                // SKUs only need to be unique inside the tenant.
                Rule::unique('inventory', 'sku')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id()))
                    ->ignore($ignoreId),
            ],
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string|max:1000',
            'category'       => 'required|in:' . implode(',', array_keys(Inventory::CATEGORIES)),
            'unit'           => 'nullable|string|max:30',
            'stock_quantity' => 'required|numeric|min:0',
            'reorder_point'  => 'required|numeric|min:0',
            'cost_price'     => 'required|numeric|min:0',
            'sale_price'     => 'required|numeric|min:0',
            'is_active'      => 'sometimes|boolean',
        ], [], [
            // Default humanising turns "sku" into "sku"; the field is an acronym.
            'sku' => 'SKU',
        ]);
    }
}