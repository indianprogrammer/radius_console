<?php

namespace App\Http\Controllers;

use App\Src\Domain\BandwidthProfile;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\RadiusClient;
use Illuminate\Http\Request;

/**
 * Bandwidth Control — RADIUS-synced, with a LOCAL mirror for display names and
 * company scoping.
 *
 * Flow (fail-closed): the RADIUS server is the system of record (SRD §4.2), so
 * every mutation is pushed to RADIUS FIRST; only on success do we persist a
 * local mirror row linking `name` + `company_id` to the returned
 * `radius_plan_id`. The list/edit pages read from the LOCAL mirror (scoped by
 * company_id) and enrich with live RADIUS values — this is what lets a user
 * assign a friendly name and see only their own company's plans. No
 * financial/billing fields live here.
 */
final class BandwidthProfileController extends Controller
{
    /**
     * List LOCAL mirrors scoped by company — shows name + RADIUS plan id.
     * Bandwidth values are enriched from the live RADIUS plan when reachable.
     */
    public function index(BandwidthProfileRepository $profiles, RadiusClient $radius)
    {
        $list = $profiles->listByCompany((int) tenant_id());

        $radiusIndex = [];
        try {
            $result = $radius->listPlans();
            $plans = is_array($result) ? ($result['plans'] ?? []) : [];
            foreach ($plans as $p) {
                $pid = is_array($p) ? ($p['id'] ?? null) : null;
                if ($pid !== null) {
                    $radiusIndex[(string) $pid] = $p;
                }
            }
        } catch (\Throwable $e) {
            // RADIUS unreachable — fall back to whatever the local mirror holds.
            $radiusIndex = null;
        }

        $rows = array_map(function (BandwidthProfile $p) use ($radiusIndex) {
            $r = is_array($radiusIndex) ? ($radiusIndex[(string) $p->radiusPlanId] ?? null) : null;
            return [
                'id' => $p->id,                     // local id (used for edit/delete routing)
                'radius_plan_id' => $p->radiusPlanId,
                'name' => $p->name,
                'company_id' => $p->companyId,
                'bandwidth_download_mbps' => $p->downloadMbps,
                'bandwidth_upload_mbps' => $p->uploadMbps,
                'vlan_id' => $r['vlan_id'] ?? null,
                'fup_threshold_gb' => $p->fupThresholdGb,
                'fup_download_mbps' => $p->fupDownloadMbps,
                'fup_upload_mbps' => $p->fupUploadMbps,
                'interim_interval' => $r['interim_interval'] ?? null,
            ];
        }, $list);

        return view('bandwidth-profiles.index', ['profiles' => $rows]);
    }

    public function create()
    {
        return view('bandwidth-profiles.create');
    }

    /**
     * Create: push to RADIUS FIRST (system of record), then save the local
     * mirror with the returned plan id. Fail-closed — if RADIUS fails, nothing
     * is stored locally.
     */
    public function store(
        Request $request,
        BandwidthProfileRepository $profiles,
        RadiusClient $radius
    ) {
        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'download_mbps' => 'required|integer|min:1',
            'upload_mbps'   => 'required|integer|min:1',
            'vlan_id'       => 'nullable|integer|min:1|max:4094',
            'fup_threshold_gb' => 'nullable|numeric|min:0',
            'fup_download_mbps'  => 'nullable|integer|min:0',
            'fup_upload_mbps'   => 'nullable|integer|min:0',
            'interim_interval'   => 'nullable|integer|min:30',
        ]);

        // 1. Push to RADIUS (system of record) FIRST.
        try {
            $created = $radius->createPlan([
                'bandwidth_download_mbps' => (int) $data['download_mbps'],
                'bandwidth_upload_mbps'  => (int) $data['upload_mbps'],
                'vlan_id'               => isset($data['vlan_id']) ? (int) $data['vlan_id'] : null,
                'fup_threshold_gb'      => isset($data['fup_threshold_gb']) ? (float) $data['fup_threshold_gb'] : null,
                'fup_download_mbps'     => $data['fup_download_mbps'] ?? null,
                'fup_upload_mbps'       => $data['fup_upload_mbps'] ?? null,
                'interim_interval'       => $data['interim_interval'] ?? 30,
            ]);
            $radiusPlanId = is_array($created)
                ? (string) ($created['plan']['id'] ?? $created['id'] ?? null)
                : null;
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        // 2. Save local mirror with the RADIUS plan id.
        $profiles->save(new BandwidthProfile(
            id: null,
            companyId: (int) tenant_id(),
            name: $data['name'],
            downloadMbps: (int) $data['download_mbps'],
            uploadMbps: (int) $data['upload_mbps'],
            dataLimitGb: null,
            durationDays: 30,
            fupThresholdGb: isset($data['fup_threshold_gb']) ? (float) $data['fup_threshold_gb'] : null,
            fupDownloadMbps: $data['fup_download_mbps'] ?? null,
            fupUploadMbps: $data['fup_upload_mbps'] ?? null,
            simultaneousUse: 1,
            radiusPlanId: $radiusPlanId,
        ));

        return redirect()
            ->route('bandwidth-profiles.index')
            ->with('status', "Bandwidth profile #{$radiusPlanId} created and synced to RADIUS.");
    }

    /**
     * Edit: read the LOCAL mirror (friendly name), enriched with live RADIUS
     * values for the bandwidth fields.
     */
    public function edit(
        Request $request,
        BandwidthProfileRepository $profiles,
        RadiusClient $radius,
        int $id
    ) {
        $local = $profiles->find($id);
        if ($local === null) {
            abort(404);
        }

        $row = [
            'name' => $local->name,
            'bandwidth_download_mbps' => $local->downloadMbps,
            'bandwidth_upload_mbps' => $local->uploadMbps,
            'vlan_id' => null,
            'fup_threshold_gb' => $local->fupThresholdGb,
            'fup_download_mbps' => $local->fupDownloadMbps,
            'fup_upload_mbps' => $local->fupUploadMbps,
            'interim_interval' => null,
        ];

        if ($local->radiusPlanId !== null) {
            try {
                $radiusRow = $radius->getPlan((int) $local->radiusPlanId);
                $r = is_array($radiusRow) ? ($radiusRow['plan'] ?? $radiusRow) : null;
                if (is_array($r)) {
                    $row['vlan_id'] = $r['vlan_id'] ?? null;
                    $row['interim_interval'] = $r['interim_interval'] ?? null;
                    if (isset($r['bandwidth_download_mbps'])) {
                        $row['bandwidth_download_mbps'] = $r['bandwidth_download_mbps'];
                    }
                    if (isset($r['bandwidth_upload_mbps'])) {
                        $row['bandwidth_upload_mbps'] = $r['bandwidth_upload_mbps'];
                    }
                }
            } catch (\Throwable $e) {
                // keep local values if RADIUS is unreachable
            }
        }

        return view('bandwidth-profiles.edit', [
            'id' => $id,
            'local' => $local,
            'profile' => $row,
        ]);
    }

    /**
     * Update: push to RADIUS FIRST (keyed by the stored radius_plan_id), then
     * sync the local mirror (name can change; company_id stays). Fail-closed.
     */
    public function update(
        Request $request,
        BandwidthProfileRepository $profiles,
        RadiusClient $radius,
        int $id
    ) {
        $local = $profiles->find($id);
        if ($local === null) {
            abort(404);
        }

        $data = $request->validate([
            'name'          => 'required|string|max:120',
            'download_mbps' => 'required|integer|min:1',
            'upload_mbps'   => 'required|integer|min:1',
            'vlan_id'       => 'nullable|integer|min:1|max:4094',
            'fup_threshold_gb' => 'nullable|numeric|min:0',
            'fup_download_mbps'  => 'nullable|integer|min:0',
            'fup_upload_mbps'   => 'nullable|integer|min:0',
            'interim_interval'   => 'nullable|integer|min:30',
        ]);

        try {
            $radius->updatePlan((int) $local->radiusPlanId, [
                'bandwidth_download_mbps' => (int) $data['download_mbps'],
                'bandwidth_upload_mbps'  => (int) $data['upload_mbps'],
                'vlan_id'               => isset($data['vlan_id']) ? (int) $data['vlan_id'] : null,
                'fup_threshold_gb'      => isset($data['fup_threshold_gb']) ? (float) $data['fup_threshold_gb'] : null,
                'fup_download_mbps'     => $data['fup_download_mbps'] ?? null,
                'fup_upload_mbps'       => $data['fup_upload_mbps'] ?? null,
                'interim_interval'       => $data['interim_interval'] ?? 30,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        $local->name = $data['name'];
        $local->downloadMbps = (int) $data['download_mbps'];
        $local->uploadMbps = (int) $data['upload_mbps'];
        $local->fupThresholdGb = isset($data['fup_threshold_gb']) ? (float) $data['fup_threshold_gb'] : null;
        $local->fupDownloadMbps = $data['fup_download_mbps'] ?? null;
        $local->fupUploadMbps = $data['fup_upload_mbps'] ?? null;
        $local->simultaneousUse = 1;
        $profiles->save($local);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Bandwidth profile updated and synced to RADIUS.']);
        }
        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile updated and synced to RADIUS.');
    }

    /**
     * Delete: remove from RADIUS FIRST (keyed by radius_plan_id), then the
     * local mirror. Fail-closed.
     */
    public function destroy(
        Request $request,
        BandwidthProfileRepository $profiles,
        RadiusClient $radius,
        int $id
    ) {
        $local = $profiles->find($id);
        if ($local === null) {
            abort(404);
        }

        try {
            $radius->deletePlan((int) $local->radiusPlanId);
        } catch (\Throwable $e) {
            return back()->withErrors(['radius' => $e->getMessage()]);
        }

        $profiles->delete($local->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Bandwidth profile deleted from RADIUS.']);
        }
        return redirect()->route('bandwidth-profiles.index')->with('status', 'Bandwidth profile deleted from RADIUS.');
    }
}
