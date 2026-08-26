<?php

namespace App\Src\Ports;

use App\Src\Domain\Nas;

/**
 * Persistence port for NAS / device entities. Every implementation MUST
 * automatically scope queries by tenant_id (SRD §3.1, §8).
 */
interface NasRepository
{
    public function save(Nas $nas): Nas;
    public function listByTenant(string $tenantId): array;
    public function find(int $id): ?Nas;
    public function delete(int $id): void;
}
