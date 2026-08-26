<?php

namespace App\Http\Controllers;

use App\Src\Domain\Plan;
use App\Src\Ports\PlanRepository;
use App\Src\Ports\RadiusClient;
use Illuminate\Http\Request;

final class PlanController extends Controller
{
    public function index(PlanRepository $plans)
    {
        $list = $plans->listByTenant(tenant_id());
        return view('plans.index', ['plans' => $list]);
    }

    public function create()
    {
        return view('plans.create');
    }

    public function store(Request $request, PlanRepository $plans, RadiusClient $radius)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'price' => 'nullable|numeric|min:0',
            'cycle' => 'nullable|string|in:monthly,quarterly,yearly',
            'download_mbps' => 'required|integer|min:1',
            'upload_mbps' => 'required|integer|min:1',
            'data_limit_gb' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'fup_threshold_gb' => 'nullable|numeric|min:0',
            'fup_download_mbps' => 'nullable|integer|min:0',
            'fup_upload_mbps' => 'nullable|integer|min:0',
            'simultaneous_use' => 'nullable|integer|min:1',
        ]);

        // Push the plan/profile to the external RADIUS server (SRD §4.2 createPlan).
        // RADIUS tracks bandwidth/limits; billing fields (price/cycle) are local-only.
        try {
            $created = $radius->createPlan([
                'name' => $data['name'],
                'bandwidth_download_mbps' => (int) $data['download_mbps'],
                'bandwidth_upload_mbps' => (int) $data['upload_mbps'],
                'data_limit_gb' => isset($data['data_limit_gb']) ? (float) $data['data_limit_gb'] : null,
                'duration_days' => (int) $data['duration_days'],
                'fup_threshold_gb' => isset($data['fup_threshold_gb']) ? (float) $data['fup_threshold_gb'] : null,
                'fup_download_mbps' => $data['fup_download_mbps'] ?? null,
                'fup_upload_mbps' => $data['fup_upload_mbps'] ?? null,
                'simultaneous_use' => $data['simultaneous_use'] ?? 1,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        $entity = new Plan(
            id: null,
            tenantId: tenant_id(),
            name: $data['name'],
            price: (float) ($data['price'] ?? 0),
            cycle: $data['cycle'] ?? 'monthly',
            downloadMbps: (int) $data['download_mbps'],
            uploadMbps: (int) $data['upload_mbps'],
            dataLimitGb: isset($data['data_limit_gb']) ? (float) $data['data_limit_gb'] : null,
            durationDays: (int) $data['duration_days'],
            fupThresholdGb: isset($data['fup_threshold_gb']) ? (float) $data['fup_threshold_gb'] : null,
            fupDownloadMbps: $data['fup_download_mbps'] ?? null,
            fupUploadMbps: $data['fup_upload_mbps'] ?? null,
            simultaneousUse: (int) ($data['simultaneous_use'] ?? 1),
            radiusProfileId: (string) ($created['id'] ?? null),
        );
        $plans->save($entity);

        return redirect()->route('plans.index')->with('status', 'Plan created.');
    }
}
