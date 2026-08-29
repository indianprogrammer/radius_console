<?php

namespace App\Src\Ports;

use App\Src\Domain\TaxRate;

interface TaxRateRepository
{
    public function save(TaxRate $tax): TaxRate;

    public function find(int $id): ?TaxRate;

    public function delete(int $id): void;

    /** @return TaxRate[] */
    public function listByTenant(string $tenantId): array;
}
