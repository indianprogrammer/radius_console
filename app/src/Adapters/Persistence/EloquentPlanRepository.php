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

        // Sync the many-to-many tax rates. Pivot rows are stamped with the
        // tenant_id so RLS isolation applies on PostgreSQL.
        $m->taxes()->sync(
            collect($plan->taxRates)->mapWithKeys(fn($tr) => [
                $tr->id => ['tenant_id' => $plan->tenantId],
            ])->all()
        );

        $plan->id = $m->id;
        return $plan;
    }

    public function find(int $id): ?Plan
    {
        $m = PlanModel::with('taxes')->find($id);
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
        return PlanModel::with('taxes')->where('tenant_id', $tenantId)
            ->get()->map(fn($m) => $this->toDomain($m))->all();
    }

    private function toDomain(PlanModel $m): Plan
    {
        $taxRates = [];
        if ($m->relationLoaded('taxes')) {
            foreach ($m->taxes as $tr) {
                $taxRates[] = new \App\Src\Domain\TaxRate(
                    id: $tr->id,
                    tenantId: $tr->tenant_id,
                    name: $tr->name,
                    rate: (float) $tr->rate,
                    type: $tr->type,
                    isDefault: (bool) $tr->is_default,
                );
            }
        }
        return new Plan(
            id: $m->id,
            tenantId: $m->tenant_id,
            name: $m->name,
            price: (float) $m->price,
            cycle: $m->cycle,
            bandwidthProfileId: $m->bandwidth_profile_id,
            taxRates: $taxRates,
        );
    }
}
