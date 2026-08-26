<?php

namespace App\Src\Adapters\Persistence;

use App\Models\Nas as NasModel;
use App\Src\Domain\Nas;
use App\Src\Ports\NasRepository;

final class EloquentNasRepository implements NasRepository
{
    public function save(Nas $nas): Nas
    {
        $model = $nas->id ? NasModel::where('tenant_id', $nas->tenantId)->findOrFail($nas->id) : new NasModel();
        $model->fill([
            'tenant_id' => $nas->tenantId,
            'name' => $nas->name,
            'nas_ip' => $nas->nasIp,
            'shared_secret' => $nas->sharedSecret,
            'nas_identifier' => $nas->nasIdentifier,
            'type' => $nas->type,
            'api_enabled' => $nas->apiEnabled,
            'description' => $nas->description,
            'radius_nas_id' => $nas->radiusNasId,
        ]);
        $model->save();
        $nas->id = $model->id;
        return $nas;
    }

    public function listByTenant(string $tenantId): array
    {
        return NasModel::where('tenant_id', $tenantId)
            ->orderBy('nas_ip')
            ->get()
            ->map(fn($m) => $this->toDomain($m))
            ->all();
    }

    public function find(int $id): ?Nas
    {
        $m = NasModel::find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function delete(int $id): void
    {
        NasModel::where('id', $id)->delete();
    }

    private function toDomain(NasModel $m): Nas
    {
        return new Nas(
            id: $m->id,
            tenantId: $m->tenant_id,
            nasIp: $m->nas_ip,
            sharedSecret: $m->shared_secret,
            name: $m->name,
            nasIdentifier: $m->nas_identifier,
            type: $m->type,
            apiEnabled: (bool) $m->api_enabled,
            description: $m->description,
            radiusNasId: $m->radius_nas_id,
        );
    }
}
