<?php

namespace App\Src\Domain;

/**
 * Pure domain entity for a RADIUS bandwidth profile.
 *
 * This holds ONLY bandwidth/network-related data (download/upload, data caps,
 * FUP, simultaneous-use). It is the local mirror of the external RADIUS
 * "plan/profile" record — RADIUS is the system of record and every mutation is
 * pushed straight to the RADIUS API (SRD §4.2). The financial side of an
 * offering lives in `Plan`, which references this profile. (Clean/Hexagonal:
 * no DB / framework imports — SRD §7.2.)
 */
final class BandwidthProfile
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public string $name,
        public int $downloadMbps,
        public int $uploadMbps,
        public ?float $dataLimitGb = null,
        public int $durationDays = 30,
        public ?float $fupThresholdGb = null,
        public ?int $fupDownloadMbps = null,
        public ?int $fupUploadMbps = null,
        public ?int $simultaneousUse = 1,
        public ?string $radiusPlanId = null,
    ) {}
}
