<?php

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use App\Src\Application\UseCases\ProvisionSubscriber;
use App\Src\Application\UseCases\UpdateSubscriber;
use App\Src\Domain\Subscriber;
use App\Src\Ports\SubscriberRepository;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\PlanRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class SubscriberController extends Controller
{
    /**
     * Address columns written by the Billing / Installation Address section
     * (resources/views/partials/subscriber-address.blade.php).
     *
     * The installation locality reuses the generic CAF `city/state/zip/country`
     * columns — the field engineer's address is the one the rest of the app
     * treats as "the" subscriber address — while billing carries its own set.
     */
    private const ADDRESS_FIELDS = [
        'billing_address', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
        'installation_address', 'installation_same_as_billing',
        'installation_landmark', 'installation_place_label',
        'city', 'state', 'zip', 'country',
        'latitude', 'longitude',
    ];

    /** Validation rules for the address section, shared by store() and update(). */
    private static function addressRules(): array
    {
        return [
            // Billing side
            'billing_address'      => 'nullable|string|max:1000',
            'billing_city'         => 'nullable|string|max:100',
            'billing_state'        => 'nullable|string|max:100',
            'billing_zip'          => 'nullable|string|max:12',
            'billing_country'      => 'nullable|string|max:100',

            // Installation side
            'installation_address'          => 'nullable|string|max:1000',
            'installation_same_as_billing'  => 'nullable|boolean',
            'installation_landmark'         => 'nullable|string|max:200',
            'installation_place_label'      => 'nullable|string|max:255',
            'city'                 => 'nullable|string|max:100',
            'state'                => 'nullable|string|max:100',
            'zip'                  => 'nullable|string|max:12',
            'country'              => 'nullable|string|max:100',

            // Map pin. Bounds are validated so a malformed submission cannot
            // store a coordinate the map would later fail to render.
            'latitude'             => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'            => 'nullable|numeric|between:-180,180|required_with:latitude',
        ];
    }

    /**
     * Reconcile the address block before it is written.
     *
     * Two things the raw request cannot express on its own:
     *  - An unchecked switch is absent from the POST, so "same as billing" has
     *    to be written as false explicitly or it would stick at its old value.
     *  - When that flag IS set, the installation locality is filled by copy
     *    from billing. Doing it server-side too means the stored row is correct
     *    even if the browser JS never ran. Only BLANK fields are copied: the
     *    installation fields are editable, and the browser clears the flag as
     *    soon as one is edited, so anything still present here was typed
     *    deliberately and must win over the copy.
     */
    private function normaliseAddress(array $update, array $data): array
    {
        $same = (bool) ($data['installation_same_as_billing'] ?? false);
        $update['installation_same_as_billing'] = $same;

        if ($same) {
            // installation field => billing field it inherits from
            $inherits = [
                'installation_address' => 'billing_address',
                'city'                 => 'billing_city',
                'state'                => 'billing_state',
                'zip'                  => 'billing_zip',
                'country'              => 'billing_country',
            ];

            foreach ($inherits as $target => $source) {
                if (($data[$target] ?? null) === null || trim((string) $data[$target]) === '') {
                    $update[$target] = $data[$source] ?? null;
                }
            }
        }

        // The pin is all-or-nothing: clearing it must null BOTH columns so a
        // stale half-coordinate can never survive.
        $lat = $data['latitude'] ?? null;
        $lon = $data['longitude'] ?? null;

        if ($lat === null || $lon === null || $lat === '' || $lon === '') {
            $update['latitude'] = null;
            $update['longitude'] = null;
        }

        return $update;
    }

    public function index(Request $request, SubscriberRepository $subscribers)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $list = $subscribers->listByTenant(tenant_id());

        $items = collect($list);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $request->query()]
        );

        return view('subscribers.index', ['subscribers' => $paginator]);
    }

    public function create(BandwidthProfileRepository $profiles, PlanRepository $plans)
    {
        return view('subscribers.create', [
            'profiles' => $profiles->listByCompany((int) tenant_id()),
            'plans' => $plans->listByTenant(tenant_id()),
        ]);
    }

    public function store(Request $request, ProvisionSubscriber $provision, \App\Models\Subscriber $subModel)
    {
        $data = $request->validate([
            // Core
            'username'              => 'nullable|string|max:64',
            'password'              => 'nullable|string|min:8',
            'plan_id'               => 'nullable|integer',
            'bandwidth_profile_id'  => 'nullable|integer',
            'mac'                   => 'nullable|string|max:17',
            'static_ip'             => 'nullable|ip',
            'expiry'                => 'nullable|date',
            // Basic
            'first_name'            => 'nullable|string|max:100',
            'last_name'             => 'nullable|string|max:100',
            'father_or_company'     => 'nullable|string|max:200',
            'mobile'                => 'nullable|string|max:20',
            'email'                 => 'nullable|email|max:255',
            // Access method
            'access_type'           => 'nullable|string|in:pppoe,ipoe',
            'pppoe_username'        => 'nullable|string|max:64|required_if:access_type,pppoe',
            'pppoe_password'        => 'nullable|string|max:128|required_if:access_type,pppoe',
            // Billing
            'billing_type'          => 'nullable|integer|min:1|max:4',
            'gstin'                => 'nullable|string|max:15',
            'installation_amount'   => 'nullable|numeric|min:0',
            'security_deposit'      => 'nullable|numeric|min:0',
            'po_number'             => 'nullable|string|max:50',
            'po_date'               => 'nullable|date',
            // Network
            'ip_mode'               => 'nullable|integer|min:1|max:4',
            'pool_name'             => 'nullable|string|max:100',
            'auto_renew'           => 'nullable|integer|in:0,1',
            'bind_mac'             => 'nullable|integer|in:0,1',
            'bind_static_ip'       => 'nullable|integer|in:0,1',
            'exclude_mac_bind'     => 'nullable|integer|in:0,1',
            'dont_suspend'         => 'nullable|integer|in:0,1',
            // Special charges (repeater rows)
            'special'               => 'nullable|array',
            'special.*.reason'      => 'nullable|string|max:200',
            'special.*.desc'        => 'nullable|string|max:500',
            'special.*.approved_by' => 'nullable|string|max:50',
            'special.*.amount'      => 'nullable|numeric|min:0',
            'special.*.type'        => 'nullable|integer|in:1,2',
        ] + self::addressRules());

        $tenant = view()->shared('tenant');
        $tenantSlug = $tenant->slug ?? 'tenant';

        // 1. Provision the core RADIUS subscriber (only RADIUS-relevant fields).
        //    The form no longer collects a RADIUS username/password, so both keys
        //    may be absent from $data entirely. Fall back to the PPPoE credentials
        //    when present, then to a generated pair.
        $provUsername = ($data['username'] ?? null)
            ?: ($data['pppoe_username'] ?? null)
            ?: strtolower(trim(($data['first_name'] ?? '') . '.' . ($data['last_name'] ?? '') . random_int(10, 99)));
        $provPassword = ($data['password'] ?? null)
            ?: ($data['pppoe_password'] ?? null)
            ?: bin2hex(random_bytes(8));

        try {
            $provisioned = $provision->execute([
                'tenant_id'             => tenant_id(),
                'username'              => $provUsername,
                'password'              => $provPassword,
                'plan_id'              => $data['plan_id'] ?? null,
                'bandwidth_profile_id'  => $data['bandwidth_profile_id'] ?? null,
                'mac'                  => $data['mac'] ?? null,
                'static_ip'            => $data['static_ip'] ?? null,
                'expiry'               => $data['expiry'] ?? null,
            ], $tenantSlug);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        // 2. Back-fill all CAF / metadata fields on the Eloquent model.
        //    (The domain entity is kept minimal; extended fields live on the model.)
        $cafFields = [
            'first_name','last_name',
            'father_or_company','mobile','email',
            'access_type','pppoe_username','pppoe_password',
            'billing_type','gstin','installation_amount','security_deposit',
            'po_number','po_date',
            'ip_mode','pool_name',
            'auto_renew','bind_mac','bind_static_ip','exclude_mac_bind',
            'dont_suspend',
            ...self::ADDRESS_FIELDS,
        ];

        // Persist CAF / metadata fields on the Eloquent model.
        $update = [];
        foreach ($cafFields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (!empty($data['special'])) {
            $update['special_charges'] = array_values($data['special']);
        }

        $update = $this->normaliseAddress($update, $data);

        if (!empty($update)) {
            $subModel->where('id', $provisioned->id)->update($update);
        }

        // Auto-generate invoice based on the subscriber's plan.
        $fresh = $subModel->find($provisioned->id);
        app(InvoiceService::class)->generateFromSubscriber($fresh);

        return redirect()->route('subscribers.index')->with('status', 'Subscriber provisioned. Invoice auto-generated.');
    }

    public function edit(int $id, SubscriberRepository $subscribers, \App\Models\Subscriber $subModel)
    {
        $sub = $subscribers->find($id);
        if ($sub === null || $sub->tenantId !== tenant_id()) {
            abort(404);
        }

        // Fetch the Eloquent model for extended CAF fields.
        $eloquent = $subModel->findOrFail($id);

        return view('subscribers.edit', [
            'subscriber' => $sub,
            'eloquent'   => $eloquent,
            'profiles'   => app(BandwidthProfileRepository::class)->listByCompany((int) tenant_id()),
            'plans'      => app(PlanRepository::class)->listByTenant(tenant_id()),
        ]);
    }

    public function update(Request $request, int $id, SubscriberRepository $subscribers, UpdateSubscriber $update, \App\Models\Subscriber $subModel)
    {
        $sub = $subscribers->find($id);
        if ($sub === null || $sub->tenantId !== tenant_id()) {
            abort(404);
        }

        $data = $request->validate([
            // Core
            'username'              => 'nullable|string|max:64',
            'password'              => 'nullable|string|min:8',
            'plan_id'               => 'nullable|integer',
            'bandwidth_profile_id'  => 'nullable|integer',
            'mac'                   => 'nullable|string|max:17',
            'static_ip'             => 'nullable|ip',
            'expiry'                => 'nullable|date',
            'status'                => 'nullable|string|in:PROSPECT,KYC_PENDING,READY,ACTIVE,SUSPENDED,EXPIRED,DELETED',
            // Basic
            'first_name'            => 'nullable|string|max:100',
            'last_name'             => 'nullable|string|max:100',
            'father_or_company'     => 'nullable|string|max:200',
            'mobile'                => 'nullable|string|max:20',
            'email'                 => 'nullable|email|max:255',
            // Access method
            'access_type'           => 'nullable|string|in:pppoe,ipoe',
            'pppoe_username'        => 'nullable|string|max:64|required_if:access_type,pppoe',
            'pppoe_password'        => 'nullable|string|max:128',
            // Billing
            'billing_type'          => 'nullable|integer|min:1|max:4',
            'gstin'                => 'nullable|string|max:15',
            'installation_amount'    => 'nullable|numeric|min:0',
            'security_deposit'      => 'nullable|numeric|min:0',
            'po_number'            => 'nullable|string|max:50',
            'po_date'               => 'nullable|date',
            // Network
            'ip_mode'               => 'nullable|integer|min:1|max:4',
            'pool_name'             => 'nullable|string|max:100',
            'auto_renew'           => 'nullable|integer|in:0,1',
            'bind_mac'             => 'nullable|integer|in:0,1',
            'bind_static_ip'       => 'nullable|integer|in:0,1',
            'exclude_mac_bind'     => 'nullable|integer|in:0,1',
            'dont_suspend'         => 'nullable|integer|in:0,1',

            // Special charges
            'special'              => 'nullable|array',
            'special.*.reason'     => 'nullable|string|max:200',
            'special.*.desc'       => 'nullable|string|max:500',
            'special.*.approved_by'=> 'nullable|string|max:50',
            'special.*.amount'     => 'nullable|numeric|min:0',
            'special.*.type'       => 'nullable|integer|in:1,2',
        ] + self::addressRules());

        $tenant = view()->shared('tenant');
        $tenantSlug = $tenant->slug ?? 'tenant';

        // Only include password in the data if it was actually supplied.
        // The form does not expose the RADIUS username, so keep the existing one
        // unless a new value was explicitly submitted.
        $radiusData = [
            'username'             => $data['username'] ?? $sub->username,
            'plan_id'             => $data['plan_id'] ?? null,
            'bandwidth_profile_id'=> $data['bandwidth_profile_id'] ?? null,
            'mac'                 => $data['mac'] ?? null,
            'static_ip'           => $data['static_ip'] ?? null,
            'expiry'              => $data['expiry'] ?? null,
        ];
        // `status` must be OMITTED rather than sent as null when the request
        // does not carry one: UpdateSubscriber keys off array_key_exists() and
        // Subscriber::$status is a non-nullable string, so a null would throw
        // and surface as a bogus "radius" error while nothing was saved.
        if (($data['status'] ?? null) !== null) {
            $radiusData['status'] = $data['status'];
        }
        if (!empty($data['password'])) {
            $radiusData['password'] = $data['password'];
        }

        try {
            $update->execute($sub, $radiusData, $tenantSlug);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        // Back-fill CAF / metadata fields on the Eloquent model.
        $cafFields = [
            'first_name','last_name',
            'father_or_company','mobile','email',
            'access_type','pppoe_username','pppoe_password',
            'billing_type','gstin','installation_amount','security_deposit',
            'po_number','po_date',
            'ip_mode','pool_name',
            'auto_renew','bind_mac','bind_static_ip','exclude_mac_bind',
            'dont_suspend',
            ...self::ADDRESS_FIELDS,
        ];

        $updateData = [];
        foreach ($cafFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        // A blank PPPoE password means "keep the existing one".
        if (array_key_exists('pppoe_password', $updateData) && $updateData['pppoe_password'] === null) {
            unset($updateData['pppoe_password']);
        }
        if (!empty($data['special'])) {
            $updateData['special_charges'] = array_values($data['special']);
        }

        $updateData = $this->normaliseAddress($updateData, $data);

        if (!empty($updateData)) {
            $subModel->where('id', $id)->update($updateData);
        }

        // Sync invoice line items with the subscriber's plan.
        $fresh = $subModel->find($id);
        app(InvoiceService::class)->generateFromSubscriber($fresh);

        return redirect()->route('subscribers.index')->with('status', 'Subscriber updated.');
    }

    public function destroy(Request $request, int $id, SubscriberRepository $subscribers, \App\Src\Ports\RadiusClient $radius)
    {
        $sub = $subscribers->find($id);
        if ($sub === null || $sub->tenantId !== tenant_id()) {
            abort(404);
        }

        // Fail-closed: delete from RADIUS first.
        try {
            if ($sub->radiusUserId !== null) {
                $radius->deleteUser($sub->radiusUserId);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['radius' => $e->getMessage()]);
        }

        $subscribers->delete($id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Subscriber deleted.']);
        }
        return redirect()->route('subscribers.index')->with('status', 'Subscriber deleted.');
    }
}
