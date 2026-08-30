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
            // Dynamic billing items (refundable | one-time | recurring)
            'billing_items'                => 'nullable|array',
            'billing_items.*.label'        => 'nullable|string|max:200',
            'billing_items.*.description'  => 'nullable|string|max:500',
            'billing_items.*.type'         => 'nullable|string|in:refundable,one-time,recurring',
            'billing_items.*.amount'       => 'nullable|numeric|min:0',
            'billing_items.*.qty'          => 'nullable|integer|min:1',
            'billing_items.*.taxable'      => 'nullable|integer|in:0,1',
            'billing_items.*.is_refundable'=> 'nullable|integer|in:0,1',
            'billing_items.*.billing_cycle'=> 'nullable|string|in:monthly,quarterly,yearly',
            'billing_items.*.status'       => 'nullable|string|in:active,inactive',
            'billing_items.*.product_id'   => 'nullable|integer',
        ]);

        $tenant = view()->shared('tenant');
        $tenantSlug = $tenant->slug ?? 'tenant';

        // 1. Provision the core RADIUS subscriber (only RADIUS-relevant fields).
        //    Auto-generate username / password if not supplied via the form.
        $provUsername = $data['username'] ?: (
            strtolower(trim(($data['first_name'] ?? '') . '.' . ($data['last_name'] ?? '') . random_int(10, 99)))
        );
        $provPassword = $data['password'] ?: bin2hex(random_bytes(8));

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
        ];

        $update = [];
        foreach ($cafFields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (!empty($data['special'])) {
            $update['special_charges'] = array_values($data['special']);
        }

        // Persist billing_items (refundable | one-time | recurring)
        $billingItems = [];
        if (!empty($data['billing_items'])) {
            foreach ($data['billing_items'] as $bi) {
                if (empty($bi['label']) && empty($bi['amount'])) {
                    continue;
                }
                $billingItems[] = [
                    'label'         => $bi['label']        ?? '',
                    'description'   => $bi['description']  ?? null,
                    'type'          => $bi['type']         ?? 'one-time',
                    'amount'        => (float) ($bi['amount']  ?? 0),
                    'qty'           => max(1, (int) ($bi['qty'] ?? 1)),
                    'taxable'       => !empty($bi['taxable']),
                    'is_refundable' => !empty($bi['is_refundable']),
                    'billing_cycle' => $bi['billing_cycle'] ?? null,
                    'status'        => $bi['status']       ?? 'active',
                    'product_id'    => $bi['product_id']  ?? null,
                ];
            }
        }
        if (!empty($billingItems)) {
            $update['billing_items'] = $billingItems;
        }

        if (!empty($update)) {
            $subModel->where('id', $provisioned->id)->update($update);
        }

        // Auto-generate invoice with the billing items attached as line items.
        if (!empty($billingItems)) {
            $fresh = $subModel->find($provisioned->id);
            app(InvoiceService::class)->generateFromSubscriber($fresh, $billingItems);
        }

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
            // Dynamic billing items (refundable | one-time | recurring)
            'billing_items'                => 'nullable|array',
            'billing_items.*.label'        => 'nullable|string|max:200',
            'billing_items.*.description'  => 'nullable|string|max:500',
            'billing_items.*.type'         => 'nullable|string|in:refundable,one-time,recurring',
            'billing_items.*.amount'       => 'nullable|numeric|min:0',
            'billing_items.*.qty'          => 'nullable|integer|min:1',
            'billing_items.*.taxable'      => 'nullable|integer|in:0,1',
            'billing_items.*.is_refundable'=> 'nullable|integer|in:0,1',
            'billing_items.*.billing_cycle'=> 'nullable|string|in:monthly,quarterly,yearly',
            'billing_items.*.status'       => 'nullable|string|in:active,inactive',
            'billing_items.*.product_id'   => 'nullable|integer',
        ]);

        $tenant = view()->shared('tenant');
        $tenantSlug = $tenant->slug ?? 'tenant';

        // Only include password in the data if it was actually supplied.
        $radiusData = [
            'username'             => $data['username'],
            'plan_id'             => $data['plan_id'] ?? null,
            'bandwidth_profile_id'=> $data['bandwidth_profile_id'] ?? null,
            'mac'                 => $data['mac'] ?? null,
            'static_ip'           => $data['static_ip'] ?? null,
            'expiry'              => $data['expiry'] ?? null,
            'status'              => $data['status'] ?? null,
        ];
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

        // Persist billing_items (refundable | one-time | recurring)
        $billingItems = [];
        if (!empty($data['billing_items'])) {
            foreach ($data['billing_items'] as $bi) {
                if (empty($bi['label']) && empty($bi['amount'])) {
                    continue;
                }
                $billingItems[] = [
                    'label'         => $bi['label']        ?? '',
                    'description'   => $bi['description']  ?? null,
                    'type'          => $bi['type']         ?? 'one-time',
                    'amount'        => (float) ($bi['amount']  ?? 0),
                    'qty'           => max(1, (int) ($bi['qty'] ?? 1)),
                    'taxable'       => !empty($bi['taxable']),
                    'is_refundable' => !empty($bi['is_refundable']),
                    'billing_cycle' => $bi['billing_cycle'] ?? null,
                    'status'        => $bi['status']       ?? 'active',
                    'product_id'    => $bi['product_id']  ?? null,
                ];
            }
        }
        if (!empty($billingItems)) {
            $updateData['billing_items'] = $billingItems;
        }

        if (!empty($updateData)) {
            $subModel->where('id', $id)->update($updateData);
        }

        // Sync invoice line items with the latest billing_items.
        if (!empty($billingItems)) {
            $fresh = $subModel->find($id);
            app(InvoiceService::class)->generateFromSubscriber($fresh, $billingItems);
        } elseif (!empty($data['billing_items']) && empty($billingItems)) {
            // All rows were empty (cleared) - just remove line items.
            \App\Models\InvoiceItem::where('subscriber_id', $id)->delete();
        }

        return redirect()->route('subscribers.index')->with('status', 'Subscriber updated. Invoice line items synced.');
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
