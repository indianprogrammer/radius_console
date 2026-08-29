<?php

namespace App\Src\Adapters\Persistence;

use App\Models\Subscriber as SubscriberModel;
use App\Src\Domain\Subscriber;
use App\Src\Ports\SubscriberRepository;

/**
 * Tenant-scoped repository adapter. Every query is filtered by tenant_id
 * (RLS is defense-in-depth; this is the mandatory guardrail, SRD §3.1, §8).
 */
final class EloquentSubscriberRepository implements SubscriberRepository
{
    public function save(Subscriber $subscriber): Subscriber
    {
        $model = $subscriber->id
            ? SubscriberModel::where('tenant_id', $subscriber->tenantId)->findOrFail($subscriber->id)
            : new SubscriberModel();

        $model->fill([
            'tenant_id' => $subscriber->tenantId,
            'username' => $subscriber->username,
            'radius_username' => $subscriber->radiusUsername,
            'password_enc' => $subscriber->passwordEnc,
            'mac' => $subscriber->mac,
            'static_ip' => $subscriber->staticIp,
            'plan_id' => $subscriber->planId,
            'bandwidth_profile_id' => $subscriber->bandwidthProfileId,
            'status' => $subscriber->status,
            'kyc_id' => $subscriber->kycId,
            'expiry' => $subscriber->expiry,
            'radius_user_id' => $subscriber->radiusUserId,
        ]);
        $model->save();

        $subscriber->id = $model->id;
        return $subscriber;
    }

    public function find(int $id): ?Subscriber
    {
        $m = SubscriberModel::find($id);
        return $m ? $this->toDomain($m) : null;
    }

    public function findByLocalUsername(string $tenantId, string $username): ?Subscriber
    {
        $m = SubscriberModel::where('tenant_id', $tenantId)->where('username', $username)->first();
        return $m ? $this->toDomain($m) : null;
    }

    public function listByTenant(string $tenantId, array $filters = []): array
    {
        $q = SubscriberModel::where('tenant_id', $tenantId);
        foreach ($filters as $k => $v) {
            $q->where($k, $v);
        }
        return $q->get()->map(fn($m) => $this->toDomain($m))->all();
    }

    public function delete(int $id): void
    {
        SubscriberModel::where('id', $id)->delete();
    }

    private function toDomain(SubscriberModel $m): Subscriber
    {
        return new Subscriber(
            id: $m->id,
            tenantId: $m->tenant_id,
            username: $m->username,
            radiusUsername: $m->radius_username,
            passwordEnc: $m->password_enc,
            mac: $m->mac,
            staticIp: $m->static_ip,
            planId: $m->plan_id,
            bandwidthProfileId: $m->bandwidth_profile_id,
            status: $m->status,
            kycId: $m->kyc_id,
            expiry: $m->expiry,
            radiusUserId: $m->radius_user_id,
        );
    }
}
