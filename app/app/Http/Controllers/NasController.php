<?php

namespace App\Http\Controllers;

use App\Src\Domain\Nas;
use App\Src\Ports\NasRepository;
use App\Src\Ports\RadiusClient;
use Illuminate\Http\Request;

final class NasController extends Controller
{
    public function index(NasRepository $nas)
    {
        $list = $nas->listByTenant(tenant_id());
        return view('nas.index', ['nas' => $list]);
    }

    public function create()
    {
        return view('nas.create');
    }

    public function store(Request $request, NasRepository $nas, RadiusClient $radius)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'nas_ip' => 'required|ip',
            'shared_secret' => 'required|string|min:3|max:255',
            'nas_identifier' => 'nullable|string|max:120',
            'type' => 'nullable|string|max:60',
            'api_enabled' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            // Push the device to the external RADIUS server (SRD §4.2 createNas).
            // RADIUS requires nas_ip + shared_secret; the rest are optional.
            $created = $radius->createNas([
                'nas_ip' => $data['nas_ip'],
                'shared_secret' => $data['shared_secret'],
                'nas_identifier' => $data['nas_identifier'] ?? $data['nas_ip'],
                'type' => $data['type'] ?? null,
                'api_enabled' => !empty($data['api_enabled']) ? 1 : 0,
                'description' => $data['description'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['radius' => $e->getMessage()]);
        }

        $entity = new Nas(
            id: null,
            tenantId: tenant_id(),
            nasIp: $data['nas_ip'],
            sharedSecret: $data['shared_secret'],
            name: $data['name'] ?? $data['nas_ip'],
            nasIdentifier: $data['nas_identifier'] ?? null,
            type: $data['type'] ?? null,
            apiEnabled: !empty($data['api_enabled']),
            description: $data['description'] ?? null,
            radiusNasId: $created['id'] ?? null,
        );
        $nas->save($entity);

        return redirect()->route('nas.index')->with('status', 'NAS device registered.');
    }
}
