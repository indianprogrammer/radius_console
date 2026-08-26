<?php

namespace App\Src\Adapters\Persistence;

use App\Models\Tenant as TenantModel;
use App\Src\Domain\Tenant;
use App\Src\Ports\TenantRepository;

final class EloquentTenantRepository implements TenantRepository
{
    public function findByDomain(string $domain): ?Tenant
    {
        $m = TenantModel::where('domain', $domain)->first();
        return $m ? $this->toDomain($m) : null;
    }

    public function find(string $id): ?Tenant
    {
        $m = TenantModel::find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function save(Tenant $tenant): Tenant
    {
        $m = $tenant->id ? TenantModel::findOrFail($tenant->id) : new TenantModel();
        $m->fill([
            'name' => $tenant->name, 'domain' => $tenant->domain, 'slug' => $tenant->slug,
            'theme_default' => $tenant->themeDefault, 'logo_url' => $tenant->logoUrl, 'status' => $tenant->status,
        ]);
        $m->save();
        $tenant->id = $m->id;
        return $tenant;
    }

    private function toDomain(TenantModel $m): Tenant
    {
        return new Tenant($m->id, $m->name, $m->domain, $m->slug, $m->theme_default, $m->logo_url, $m->status);
    }
}
