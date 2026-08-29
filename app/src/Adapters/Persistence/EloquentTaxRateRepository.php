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

        // Ensure only one default per tenant.
        if ($tax->isDefault) {
            TaxRateModel::where('tenant_id', $tax->tenantId)->where('id', '!=', $tax->id ?? 0)->update(['is_default' => false]);
        }

        $m->fill([
            'tenant_id' => $tax->tenantId,
            'name' => $tax->name,
            'rate' => $tax->rate,
            'type' => $tax->type,
            'is_default' => $tax->isDefault,
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
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn($m) => $this->toDomain($m))
            ->all();
    }

    public function defaultFor(string $tenantId): ?TaxRate
    {
        $m = TaxRateModel::where('tenant_id', $tenantId)->where('is_default', true)->first();
        return $m ? $this->toDomain($m) : null;
    }

    private function toDomain(TaxRateModel $m): TaxRate
    {
        return new TaxRate(
            id: $m->id,
            tenantId: $m->tenant_id,
            name: $m->name,
            rate: (float) $m->rate,
            type: $m->type,
            isDefault: (bool) $m->is_default,
        );
    }
}
