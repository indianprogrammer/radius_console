<?php

namespace App\Src\Domain;

/**
 * Pure domain entity for a NAS / access device (SRD §5.x).
 * No framework imports (Clean/Hexagonal).
 */
final class Nas
{
    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $nasIp,
        public string $sharedSecret,
        public ?string $name = null,
        public ?string $nasIdentifier = null,
        public ?string $type = null,
        public bool $apiEnabled = false,
        public ?string $apiHost = null,
        public ?string $apiPort = null,
        public ?string $apiUsername = null,
        public ?string $apiPassword = null,
        public ?string $description = null,
        public ?int $radiusNasId = null,
    ) {}
}
