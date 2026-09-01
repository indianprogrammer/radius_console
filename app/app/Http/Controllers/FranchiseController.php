<?php

namespace App\Http\Controllers;

use App\Models\Franchise;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Franchises / LCOs — Franchise Management (SRD §5.0 #3, §5.4).
 *
 * Every query is tenant scoped. `balance` is system-maintained: it is seeded
 * from the opening balance on create and is NOT accepted from the edit form,
 * so a wallet figure can never be silently overwritten by a form post.
 */
final class FranchiseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $franchises = Franchise::query()
            ->where('tenant_id', tenant_id())
            ->with('parent')
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('franchises.index', [
            'franchises' => $franchises,
            'search'     => $request->query('q'),
            'type'       => $request->query('type'),
            'status'     => $request->query('status'),
            'totals'     => $this->summary(),
        ]);
    }

    public function create()
    {
        return view('franchises.create', [
            'franchise' => new Franchise([
                'code'            => Franchise::nextCode(tenant_id()),
                'type'            => 'franchise',
                'commission_type' => 'percentage',
                'commission_rate' => 0,
                'credit_limit'    => 0,
                'balance'         => 0,
                'status'          => 'active',
            ]),
            'parents' => $this->parents(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['tenant_id'] = tenant_id();
        // `code` may be absent (nullable) or nulled by ConvertEmptyStringsToNull.
        $data['code'] = ($data['code'] ?? null) ?: Franchise::nextCode(tenant_id());
        // Opening balance seeds the wallet; afterwards it is system-maintained.
        $data['balance'] = (float) ($data['balance'] ?? 0);

        $franchise = Franchise::create($data);

        return redirect()->route('franchises.index')
            ->with('status', "Franchise {$franchise->code} created.");
    }

    public function edit(int $id)
    {
        $franchise = Franchise::where('tenant_id', tenant_id())->findOrFail($id);

        return view('franchises.edit', [
            'franchise' => $franchise,
            'parents'   => $this->parents($franchise->id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $franchise = Franchise::where('tenant_id', tenant_id())->findOrFail($id);

        $data = $this->validateData($request, $franchise->id);
        // The wallet balance is never taken from the edit form.
        unset($data['balance']);

        $franchise->update($data);

        return redirect()->route('franchises.index')
            ->with('status', "Franchise {$franchise->code} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $franchise = Franchise::where('tenant_id', tenant_id())->findOrFail($id);
        $code = $franchise->code;

        if ($franchise->children()->exists()) {
            $message = "Franchise {$code} has sub-franchises — reassign them first.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('franchises.index')->withErrors(['franchise' => $message]);
        }

        $franchise->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Franchise {$code} deleted."]);
        }

        return redirect()->route('franchises.index')->with('status', "Franchise {$code} deleted.");
    }

    /**
     * @param int|null $ignoreId Franchise being edited (excluded from unique + parent list).
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('franchises', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', tenant_id()))
                    ->ignore($ignoreId),
            ],
            'name'            => 'required|string|max:150',
            'type'            => 'required|in:' . implode(',', array_keys(Franchise::TYPES)),
            'parent_id'       => [
                'nullable', 'integer',
                Rule::exists('franchises', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
                // A franchise cannot be its own parent.
                Rule::notIn($ignoreId === null ? [] : [$ignoreId]),
            ],
            'contact_person'  => 'nullable|string|max:150',
            'email'           => 'nullable|email|max:150',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:100',
            'pincode'         => 'nullable|string|max:12',
            'gst_number'      => 'nullable|string|max:20',
            'pan_number'      => 'nullable|string|max:20',
            'commission_type' => 'required|in:' . implode(',', array_keys(Franchise::COMMISSION_TYPES)),
            'commission_rate' => 'required|numeric|min:0',
            'credit_limit'    => 'required|numeric|min:0',
            'balance'         => 'nullable|numeric',
            'status'          => 'required|in:' . implode(',', array_keys(Franchise::STATUSES)),
            'notes'           => 'nullable|string|max:1000',
        ]);
    }

    /** Franchises selectable as a parent (excluding the one being edited). */
    private function parents(?int $excludeId = null)
    {
        return Franchise::where('tenant_id', tenant_id())
            ->when($excludeId, fn ($q, $id) => $q->whereKeyNot($id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /** Header tiles: counts and wallet exposure across the tenant. */
    private function summary(): array
    {
        // Each call returns a fresh builder, so no cloning is needed.
        $base = fn () => Franchise::where('tenant_id', tenant_id());

        return [
            'total'    => $base()->count(),
            'active'   => $base()->where('status', 'active')->count(),
            'balance'  => round((float) $base()->sum('balance'), 2),
            'exposure' => round((float) $base()->sum('credit_limit'), 2),
        ];
    }
}
