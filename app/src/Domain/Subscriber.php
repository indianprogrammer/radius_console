<?php

namespace App\Src\Domain;

/**
 * Pure domain entity. No DB / framework imports (SRD §7.2).
 * Note: password is AES-256 ENCRYPTED (recoverable) only for the local
 * record if needed; the RADIUS server stores its own bcrypt+reversible copy.
 * The business "truth" (plan, wallet) lives here; RADIUS is "network truth".
 */
final class Subscriber
{
    public const STATUS_PROSPECT = 'PROSPECT';
    public const STATUS_KYC_PENDING = 'KYC_PENDING';
    public const STATUS_READY = 'READY';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_DELETED = 'DELETED';

    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $username,            // tenant-local username
        public ?string $radiusUsername = null, // tenant-namespaced, set on provision
        public ?string $passwordEnc = null, // AES-256 encrypted, nullable
        public ?string $mac = null,
        public ?string $staticIp = null,
        public ?int $planId = null,
        public ?int $bandwidthProfileId = null,
        public string $status = self::STATUS_PROSPECT,
        public ?int $kycId = null,
        public ?string $expiry = null,
        public ?int $radiusUserId = null,
    ) {}

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Derive the RADIUS namespaced username (SRD §4.1.1). */
    public function radiusUsername(string $tenantSlug): string
    {
        return $tenantSlug . '_' . $this->username;
    }
}
