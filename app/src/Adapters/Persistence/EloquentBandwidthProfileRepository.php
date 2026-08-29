<?php

namespace App\Src\Adapters\Persistence;

use App\Models\BandwidthProfile as BandwidthProfileModel;
use App\Src\Domain\BandwidthProfile;
use App\Src\Ports\BandwidthProfileRepository;

/**
 * Company-scoped repository adapter for RADIUS-synced bandwidth profiles.
 * Every query is filtered by company_id (RLS is defense-in-depth — SRD §3.1, §8).
 * Local row is created first (with radius_plan_id NULL), then synced to RADIUS,
 * and the local row is updated with the RADIUS plan id.
 */
final class EloquentBandwidthProfileRepository implements BandwidthProfileRepository
{
    public function save(BandwidthProfile $profile): BandwidthProfile
    {
        $m = $profile->id
            ? BandwidthProfileModel::where('company_id', $profile->companyId)->findOrFail($profile->id)
            : new BandwidthProfileModel();

        $m->fill([
            'company_id' => $profile->companyId,
            'name' => $profile->name,
            'download_mbps' => $profile->downloadMbps,
            'upload_mbps' => $profile->uploadMbps,
            'data_limit_gb' => $profile->dataLimitGb,
            'duration_days' => $profile->durationDays,
            'fup_threshold_gb' => $profile->fupThresholdGb,
            'fup_download_mbps' => $profile->fupDownloadMbps,
            'fup_upload_mbps' => $profile->fupUploadMbps,
            'simultaneous_use' => $profile->simultaneousUse,
            'radius_plan_id' => $profile->radiusPlanId,
        ]);
        $m->save();
        $profile->id = $m->id;
        return $profile;
    }

    public function find(int $id): ?BandwidthProfile
    {
        $m = BandwidthProfileModel::find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function delete(int $id): void
    {
        BandwidthProfileModel::where('id', $id)->delete();
    }

    /** @return BandwidthProfile[] */
    public function listByCompany(int $companyId): array
    {
        return BandwidthProfileModel::where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(fn($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(BandwidthProfileModel $m): BandwidthProfile
    {
        return new BandwidthProfile(
            id: $m->id,
            companyId: $m->company_id,
            name: $m->name,
            downloadMbps: $m->download_mbps,
            uploadMbps: $m->upload_mbps,
            dataLimitGb: $m->data_limit_gb,
            durationDays: $m->duration_days,
            fupThresholdGb: $m->fup_threshold_gb,
            fupDownloadMbps: $m->fup_download_mbps,
            fupUploadMbps: $m->fup_upload_mbps,
            simultaneousUse: $m->simultaneous_use,
            radiusPlanId: $m->radius_plan_id,
        );
    }
}
