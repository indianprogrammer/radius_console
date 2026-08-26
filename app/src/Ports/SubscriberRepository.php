<?php

namespace App\Src\Ports;

use App\Src\Domain\Subscriber;

/**
 * Persistence port for Subscriber entities. All implementations MUST
 * automatically scope every query by the current tenant_id (SRD §3.1, §8).
 * No use-case may bypass this port with raw SQL.
 */
interface SubscriberRepository
{
    public function save(Subscriber $subscriber): Subscriber;

    public function find(int $id): ?Subscriber;

    public function findByLocalUsername(string $tenantId, string $username): ?Subscriber;

    /** @return Subscriber[] */
    public function listByTenant(string $tenantId, array $filters = []): array;

    public function delete(int $id): void;
}
