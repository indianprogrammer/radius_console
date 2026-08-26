<?php

namespace App\Src\Ports;

use App\Src\Domain\Plan;

interface PlanRepository
{
    public function save(Plan $plan): Plan;

    public function find(int $id): ?Plan;

    /** @return Plan[] */
    public function listByTenant(string $tenantId): array;
}
