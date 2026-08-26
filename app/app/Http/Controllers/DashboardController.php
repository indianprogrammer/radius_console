<?php

namespace App\Http\Controllers;

use App\Src\Ports\SubscriberRepository;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, SubscriberRepository $subscribers)
    {
        $tenantId = tenant_id(); // helper below resolves resolved tenant
        $stats = [
            'subscribers' => count($subscribers->listByTenant($tenantId)),
            'active' => count($subscribers->listByTenant($tenantId, ['status' => 'active'])),
        ];
        return view('dashboard', compact('stats'));
    }
}
