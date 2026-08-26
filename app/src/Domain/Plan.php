<?php

namespace App\Src\Domain;

final class Plan
{
    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $name,
        public float $price,
        public string $cycle,           // monthly|quarterly|yearly
        public int $downloadMbps,
        public int $uploadMbps,
        public ?float $dataLimitGb = null,
        public int $durationDays = 30,
        public ?float $fupThresholdGb = null,
        public ?int $fupDownloadMbps = null,
        public ?int $fupUploadMbps = null,
        public ?int $simultaneousUse = 1,
        public ?string $radiusProfileId = null,
    ) {}
}
