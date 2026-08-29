<?php

namespace App\Src\Ports;

use App\Src\Domain\BandwidthProfile;

interface BandwidthProfileRepository
{
    public function save(BandwidthProfile $profile): BandwidthProfile;

    public function find(int $id): ?BandwidthProfile;

    public function delete(int $id): void;

    /** @return BandwidthProfile[] */
    public function listByCompany(int $companyId): array;
}
