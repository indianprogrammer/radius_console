<?php

namespace App\Http\Controllers;

use App\Src\Application\UseCases\ProvisionSubscriber;
use App\Src\Domain\Subscriber;
use App\Src\Ports\SubscriberRepository;
use Illuminate\Http\Request;

final class SubscriberController extends Controller
{
    public function index(SubscriberRepository $subscribers)
    {
        $list = $subscribers->listByTenant(tenant_id());
        return view('subscribers.index', ['subscribers' => $list]);
    }

    public function create(\App\Src\Ports\BandwidthProfileRepository $profiles, \App\Src\Ports\PlanRepository $plans)
    {
        return view('subscribers.create', [
            'profiles' => $profiles->listByCompany((int) tenant_id()),
            'plans' => $plans->listByTenant(tenant_id()),
        ]);
    }

    public function store(Request $request, ProvisionSubscriber $provision, \App\Src\Ports\TenantRepository $tenants)
    {
        $data = $request->validate([
            'username' => 'required|string|max:64',
            'password' => 'required|string|min:8',
            'plan_id' => 'nullable|integer',
            'bandwidth_profile_id' => 'nullable|integer',
            'mac' => 'nullable|string',
            'static_ip' => 'nullable|ip',
            'expiry' => 'nullable|date',
        ]);

        $tenant = view()->shared('tenant');
        $tenantSlug = $tenant->slug ?? 'tenant';

        try {
            $provision->execute(
                [
                    'tenant_id' => tenant_id(),
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'plan_id' => $data['plan_id'] ?? null,
                    'bandwidth_profile_id' => $data['bandwidth_profile_id'] ?? null,
                    'mac' => $data['mac'] ?? null,
                    'static_ip' => $data['static_ip'] ?? null,
                    'expiry' => $data['expiry'] ?? null,
                ],
                $tenantSlug
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        return redirect()->route('subscribers.index')->with('status', 'Subscriber provisioned.');
    }
}
