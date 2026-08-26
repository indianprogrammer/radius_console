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
            'tenant_id' => $plan->tenantId, 'name' => $plan->name, 'price' => $plan->price, 'cycle' => $plan->cycle,
            'download_mbps' => $plan->downloadMbps, 'upload_mbps' => $plan->uploadMbps,
            'data_limit_gb' => $plan->dataLimitGb, 'duration_days' => $plan->durationDays,
            'fup_threshold_gb' => $plan->fupThresholdGb, 'fup_download_mbps' => $plan->fupDownloadMbps,
            'fup_upload_mbps' => $plan->fupUploadMbps, 'simultaneous_use' => $plan->simultaneousUse,
            'radius_profile_id' => $plan->radiusProfileId,
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

    public function listByTenant(string $tenantId): array
    {
        return PlanModel::where('tenant_id', $tenantId)->get()->map(fn($m) => $this->toDomain($m))->all();
    }

    private function toDomain(PlanModel $m): Plan
    {
        return new Plan(
            id: $m->id, tenantId: $m->tenant_id, name: $m->name, price: $m->price, cycle: $m->cycle,
            downloadMbps: $m->download_mbps, uploadMbps: $m->upload_mbps, dataLimitGb: $m->data_limit_gb,
            durationDays: $m->duration_days, fupThresholdGb: $m->fup_threshold_gb,
            fupDownloadMbps: $m->fup_download_mbps, fupUploadMbps: $m->fup_upload_mbps,
            simultaneousUse: $m->simultaneous_use, radiusProfileId: $m->radius_profile_id,
        );
    }
}
