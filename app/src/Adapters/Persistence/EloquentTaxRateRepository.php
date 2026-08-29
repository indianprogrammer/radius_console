<?php

namespace App\Src\Adapters\Persistence;

use App\Models\TaxRate as TaxRateModel;
use App\Src\Domain\TaxRate;
use App\Src\Ports\TaxRateRepository;

/**
 * Tenant-scoped repository adapter for managed tax rates.
 */
final class EloquentTaxRateRepository implements TaxRateRepository
{
    public function save(TaxRate $tax): TaxRate
    {
        $m = $tax->id ? TaxRateModel::where('tenant_id', $tax->tenantId)->findOrFail($tax->id) : new TaxRateModel();
        $m->fill([
            'tenant_id' => $tax->tenantId,
            'name' => $tax->name,
            'rate' => $tax->rate,
            'type' => $tax->type,
        ]);
        $m->save();
        $tax->id = $m->id;
        return $tax;
    }

    public function find(int $id): ?TaxRate
    {
        $m = TaxRateModel::find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function delete(int $id): void
    {
        // Detach from any plans via the pivot (cascadeOnDelete also covers it),
        // then remove the tax rate itself.
        \App\Models\TaxRate::where('id', $id)->first()?->plans()->detach();
        TaxRateModel::where('id', $id)->delete();
    }

    public function listByTenant(string $tenantId): array
    {
        return TaxRateModel::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(TaxRateModel $m): TaxRate
    {
        return new TaxRate(
            id: $m->id,
            tenantId: $m->tenant_id,
            name: $m->name,
            rate: (float) $m->rate,
            type: $m->type,
        );
    }
}
