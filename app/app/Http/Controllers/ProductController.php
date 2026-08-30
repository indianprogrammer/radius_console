<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * JSON autocomplete for billing-item product picker.
     * Returns active products matching the search query.
     */
    public function autocomplete(Request $request)
    {
        $q = $request->query('q', '');
        $products = Product::query()
            ->where('tenant_id', tenant_id())
            ->where('is_active', true)
            ->when($q, fn($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($q) . '%']))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'category', 'default_amount', 'unit']);

        return response()->json($products);
    }

    public function index(Request $request)
    {
        $query = Product::query()->where('tenant_id', tenant_id());

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        $products = $query->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products'  => $products,
            'search'    => $search,
            'category'  => $cat,
        ]);
    }

    public function create()
    {
        $taxes = $this->taxes();
        return view('products.create', [
            'product' => new Product([
                'category'        => 'one-time',
                'is_active'       => true,
                'default_amount'  => 0,
                'unit'            => 'pcs',
            ]),
            'taxes'   => $taxes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['tenant_id']  = tenant_id();
        $data['is_active']  = $request->boolean('is_active');

        $product = Product::create($data);
        $this->syncTaxes($product, $request);

        return redirect()->route('products.index')
            ->with('status', 'Product / service created successfully.');
    }

    public function edit(int $id)
    {
        $product = Product::where('tenant_id', tenant_id())->findOrFail($id);
        return view('products.edit', [
            'product' => $product,
            'taxes'   => $this->taxes(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::where('tenant_id', tenant_id())->findOrFail($id);
        $data = $this->validateData($request, $product->id);

        $data['is_active']  = $request->boolean('is_active');

        $product->update($data);
        $this->syncTaxes($product, $request);

        return redirect()->route('products.index')
            ->with('status', 'Product / service updated successfully.');
    }

    public function destroy(int $id)
    {
        $product = Product::where('tenant_id', tenant_id())->findOrFail($id);
        $product->delete();

        return redirect()->route('products.index')
            ->with('status', 'Product / service deleted.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'           => 'required|string|max:150',
            'description'    => 'nullable|string|max:1000',
            'category'       => 'required|in:one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'unit'           => 'nullable|string|max:30',
            'is_active'      => 'sometimes|boolean',
            'tax_rate_ids'   => 'nullable|array',
            'tax_rate_ids.*' => 'integer|exists:tax_rates,id',
        ]);
    }

    private function taxes()
    {
        return TaxRate::where('tenant_id', tenant_id())->orderBy('name')->get();
    }

    private function syncTaxes(Product $product, Request $request): void
    {
        $product->taxes()->sync($request->input('tax_rate_ids', []));
    }
}
