<?php

namespace App\Src\Ports;

use App\Src\Domain\Tenant;

interface TenantRepository
{
    public function findByDomain(string $domain): ?Tenant;

    public function find(string $id): ?Tenant;

    public function save(Tenant $tenant): Tenant;
}
