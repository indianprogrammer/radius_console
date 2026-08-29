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
        ]);
        $m->save();
        $plan->id = $m->id;
        return $plan;
    }

    public function find(int $id): ?Plan
    {
        $m = PlanModel::find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function delete(int $id): void
    {
        PlanModel::where('id', $id)->delete();
    }

    public function listByTenant(string $tenantId): array
    {
        return PlanModel::where('tenant_id', $tenantId)->get()->map(fn($m) => $this->toDomain($m))->all();
    }

    private function toDomain(PlanModel $m): Plan
    {
        return new Plan(
            id: $m->id,
            tenantId: $m->tenant_id,
            name: $m->name,
            price: (float) $m->price,
            cycle: $m->cycle,
            bandwidthProfileId: $m->bandwidth_profile_id,
        );
    }
}
