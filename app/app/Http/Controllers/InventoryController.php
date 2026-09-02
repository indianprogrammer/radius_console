<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Inventory::query()->where('tenant_id', tenant_id());

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        $lowOnly = $request->boolean('low_stock');
        if ($lowOnly) {
            $query->whereRaw('stock_quantity <= reorder_point');
        }

        $items = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', [
            'items'     => $items,
            'search'    => $search,
            'category'  => $cat,
            'lowStock'  => $lowOnly,
        ]);
    }

    public function create()
    {
        return view('inventory.create', [
            'item' => new Inventory([
                'category'      => 'physical',
                'unit'          => 'pcs',
                'stock_quantity'=> 0,
                'reorder_point' => 0,
                'cost_price'    => 0,
                'sale_price'    => 0,
                'is_active'     => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['tenant_id'] = tenant_id();
        $data['is_active'] = $request->boolean('is_active');

        Inventory::create($data);

        return redirect()->route('inventory.index')
            ->with('status', 'Inventory item created successfully.');
    }

    public function edit(int $id)
    {
        $item = Inventory::where('tenant_id', tenant_id())->findOrFail($id);
        return view('inventory.edit', [
            'item' => $item,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $item = Inventory::where('tenant_id', tenant_id())->findOrFail($id);
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active');

        $item->update($data);

        return redirect()->route('inventory.index')
            ->with('status', 'Inventory item updated successfully.');
    }

    public function destroy(int $id)
    {
        $item = Inventory::where('tenant_id', tenant_id())->findOrFail($id);
        $item->delete();

        return redirect()->route('inventory.index')
            ->with('status', 'Inventory item deleted.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'sku'           => 'required|string|max:100',
            'name'          => 'required|string|max:200',
            'description'   => 'nullable|string|max:1000',
            'category'      => 'required|in:physical,digital,service,accessory',
            'unit'          => 'nullable|string|max:30',
            'stock_quantity'=> 'required|numeric|min:0',
            'reorder_point' => 'required|numeric|min:0',
            'cost_price'    => 'required|numeric|min:0',
            'sale_price'    => 'required|numeric|min:0',
            'is_active'     => 'sometimes|boolean',
        ]);
    }
}