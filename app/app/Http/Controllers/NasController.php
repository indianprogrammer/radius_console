<?php

namespace App\Http\Controllers;

use App\Src\Domain\Nas;
use App\Src\Ports\NasRepository;
use App\Src\Ports\RadiusClient;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class NasController extends Controller
{
    /**
     * List NAS devices. The index reflects what is on the external RADIUS
     * server (the system of record for devices). Local records only supply
     * friendly labels + tenant scoping. If RADIUS is unreachable we fall back
     * to the local mirror so the UI still renders (SRD §4.1 resilience).
     */
    public function index(Request $request, NasRepository $nas, RadiusClient $radius)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        try {
            $api = $radius->listNas();
            $rows = $api['nas'] ?? [];
            $labels = collect($nas->listByTenant(tenant_id()))
                ->keyBy(fn(Nas $n) => $n->radiusNasId);
            $list = array_map(function (array $r) use ($labels) {
                $label = $r['id'] !== null && isset($labels[$r['id']])
                    ? $labels[$r['id']]->name : null;
                return new Nas(
                    id: null,
                    tenantId: tenant_id(),
                    nasIp: $r['nas_ip'],
                    sharedSecret: '', // never expose the secret to the UI
                    name: $label,
                    nasIdentifier: $r['nas_identifier'] ?? null,
                    type: $r['type'] ?? null,
                    apiEnabled: !empty($r['api_enabled']),
                    apiHost: $r['api_host'] ?? null,
                    apiPort: $r['api_port'] ?? null,
                    apiUsername: $r['api_username'] ?? null,
                    apiPassword: $r['api_password'] ?? null,
                    description: $r['description'] ?? null,
                    radiusNasId: $r['id'] ?? null,
                );
            }, $rows);
        } catch (\Throwable $e) {
            $list = $nas->listByTenant(tenant_id());
        }

        // Server-side pagination: the source of truth is the external RADIUS
        // API (fetched per request). Slice + wrap in a LengthAwarePaginator so
        // the view can render real pagination links via {{ $nas->links() }}.
        $items = collect($list);
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $request->query()]
        );

        return view('nas.index', ['nas' => $paginator]);
    }

    public function create()
    {
        return view('nas.create');
    }

    public function store(Request $request, NasRepository $nas, RadiusClient $radius)
    {
        $data = $this->validated($request);

        // Fail-closed: push to RADIUS FIRST. If RADIUS rejects, the local
        // change is rejected too — guarantees the two can never diverge.
        try {
            $created = $radius->createNas($this->radiusPayload($data));
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        $entity = $this->toEntity($data, $created['id'] ?? null);
        $nas->save($entity);

        return redirect()->route('nas.index')->with('status', 'NAS device registered and synced to RADIUS.');
    }

    public function edit(Request $request, NasRepository $nas, RadiusClient $radius, int $id)
    {
        // $id is the external RADIUS id (system of record), passed from the
        // list view. The local mirror only supplies the friendly label, so it
        // is optional — a device can exist in RADIUS without a local row.
        $local = $nas->findByRadiusNasId($id);

        // Prefer the live RADIUS record for the form values; fall back to the
        // local mirror only if RADIUS is unreachable. 404 only if BOTH missing.
        $radiusRow = null;
        try {
            $radiusRow = $radius->getNas($id);
        } catch (\Throwable $e) {
            $radiusRow = null;
        }
        // getNas() returns ['nas' => [...]] when found.
        $radiusData = ($radiusRow && isset($radiusRow['nas'])) ? $radiusRow['nas'] : null;

        if ($radiusData === null) {
            if ($local === null) {
                abort(404);
            }
            $row = [
                'nas_ip' => $local->nasIp,
                'shared_secret' => '',
                'nas_identifier' => $local->nasIdentifier,
                'type' => $local->type,
                'api_enabled' => $local->apiEnabled ? 1 : 0,
                'api_host' => $local->apiHost,
                'api_port' => $local->apiPort,
                'api_username' => $local->apiUsername,
                'api_password' => $local->apiPassword,
                'description' => $local->description,
            ];
            $name = $local->name;
        } else {
            $row = $radiusData;
            $name = $local?->name;
        }

        return view('nas.edit', [
            'id' => $id,
            'name' => $name,
            'nas' => $row,
        ]);
    }

    public function update(Request $request, NasRepository $nas, RadiusClient $radius, int $id)
    {
        // $id is the external RADIUS id (system of record). The local mirror is
        // optional and is upserted below; we do NOT require it to pre-exist.
        $data = $this->validated($request);

        // Fail-closed sync: push to RADIUS before persisting locally.
        try {
            $radius->updateNas($id, $this->radiusPayload($data));
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        // Upsert the local mirror (friendly label + tenant scope). Create one
        // if this RADIUS device had no mirror row yet.
        $local = $nas->findByRadiusNasId($id);
        $entity = $this->toEntity($data, $id);
        if ($local !== null) {
            $entity->id = $local->id;
        }
        $nas->save($entity);

        // For AJAX/fetch (the edit form posts via standard form submit too),
        // return JSON so the caller does not follow a 302 redirect.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'NAS device updated and synced to RADIUS.']);
        }
        return redirect()->route('nas.index')->with('status', 'NAS device updated and synced to RADIUS.');
    }

    public function destroy(Request $request, NasRepository $nas, RadiusClient $radius, int $id)
    {
        // $id is the external RADIUS id (system of record). The local mirror is
        // optional; deleting a device that has no mirror is still allowed.
        // Fail-closed: delete on RADIUS first, then locally (best-effort).
        try {
            $radius->deleteNas($id);
        } catch (\Throwable $e) {
            return back()->withErrors(['radius' => $e->getMessage()]);
        }

        $local = $nas->findByRadiusNasId($id);
        if ($local !== null) {
            $nas->delete($local->id);
        }

        // For AJAX/fetch (the delete button uses fetch()), return JSON rather
        // than a 302 — fetch() follows the redirect as DELETE against /nas and
        // that yields 405, which the UI misreports as a failure.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'NAS device deleted and removed from RADIUS.']);
        }
        return redirect()->route('nas.index')->with('status', 'NAS device deleted and removed from RADIUS.');
    }

    // ---- helpers --------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'nullable|string|max:120',
            'nas_ip' => 'required|ip',
            'shared_secret' => 'required|string|min:3|max:255',
            'nas_identifier' => 'nullable|string|max:120',
            'type' => 'nullable|string|in:mikrotik,cisco,ubiquiti,aruba,other',
            'api_enabled' => 'nullable|boolean',
            'api_host' => 'nullable|string|max:255',
            'api_port' => 'nullable|string|max:20',
            'api_username' => 'nullable|string|max:120',
            'api_password' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);
    }

    /** Map form data to the external RADIUS /api/nas payload (SRD §4.2). */
    private function radiusPayload(array $d): array
    {
        return [
            'nas_ip' => $d['nas_ip'],
            'shared_secret' => $d['shared_secret'],
            'nas_identifier' => $d['nas_identifier'] ?? $d['nas_ip'],
            'type' => $d['type'] ?? null,
            'api_enabled' => !empty($d['api_enabled']) ? 1 : 0,
            'api_host' => $d['api_host'] ?? null,
            'api_port' => $d['api_port'] ?? null,
            'api_username' => $d['api_username'] ?? null,
            'api_password' => $d['api_password'] ?? null,
            'description' => $d['description'] ?? null,
        ];
    }

    private function toEntity(array $d, ?int $radiusNasId): Nas
    {
        return new Nas(
            id: null,
            tenantId: tenant_id(),
            nasIp: $d['nas_ip'],
            sharedSecret: $d['shared_secret'],
            name: $d['name'] ?? $d['nas_ip'],
            nasIdentifier: $d['nas_identifier'] ?? null,
            type: $d['type'] ?? null,
            apiEnabled: !empty($d['api_enabled']),
            apiHost: $d['api_host'] ?? null,
            apiPort: $d['api_port'] ?? null,
            apiUsername: $d['api_username'] ?? null,
            apiPassword: $d['api_password'] ?? null,
            description: $d['description'] ?? null,
            radiusNasId: $radiusNasId,
        );
    }
}
