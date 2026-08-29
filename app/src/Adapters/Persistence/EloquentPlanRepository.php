<?php

namespace App\Src\Adapters\Persistence;

use App\Models\Plan as PlanModel;
use App\Src\Domain\Plan;
use App\Src\Ports\PlanRepository;

final class EloquentPlanRepository implements PlanRepository
{
    public function save(Plan $plan): Plan
    {
        $m = $plan->id ? PlanModel::where('tenant_id', $plan->tenantId)->findOrFail($plan->id) : new PlanModel();
        $m->fill([
            'tenant_id' => $plan->tenantId,
            'name' => $plan->name,
            'price' => $plan->price,
            'cycle' => $plan->cycle,
            'bandwidth_profile_id' => $plan->bandwidthProfileId,
            'tax_rate' => $plan->taxRate,
            'tax_rate_id' => $plan->taxRateId,
        ]);
        $m->save();
        $plan->id = $m->id;
        return $plan;
    }

    public function find(int $id): ?Plan
    {
        $m = PlanModel::with('taxRate')->find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function delete(int $id): void
    {
        // Subscribers reference plans via plan_id (nullable FK). Detach them
        // first so the deletion doesn't violate the foreign key constraint.
        \App\Models\Subscriber::where('plan_id', $id)->update(['plan_id' => null]);
        PlanModel::where('id', $id)->delete();
    }

    public function listByTenant(string $tenantId): array
    {
        return PlanModel::with('taxRate')->where('tenant_id', $tenantId)
            ->get()->map(fn($m) => $this->toDomain($m))->all();
    }

    private function toDomain(PlanModel $m): Plan
    {
        $plan = new Plan(
            id: $m->id,
            tenantId: $m->tenant_id,
            name: $m->name,
            price: (float) $m->price,
            cycle: $m->cycle,
            bandwidthProfileId: $m->bandwidth_profile_id,
            taxRate: (float) ($m->tax_rate ?? 0),
            taxRateId: $m->tax_rate_id,
        );
        if ($m->relationLoaded('taxRate') && $m->taxRate) {
            $tr = $m->taxRate;
            $plan->linkedTaxRate = new \App\Src\Domain\TaxRate(
                id: $tr->id,
                tenantId: $tr->tenant_id,
                name: $tr->name,
                rate: (float) $tr->rate,
                type: $tr->type,
                isDefault: (bool) $tr->is_default,
            );
        }
        return $plan;
    }
}
