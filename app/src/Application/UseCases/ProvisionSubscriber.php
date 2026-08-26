<?php

namespace App\Src\Application\UseCases;

use App\Src\Domain\Subscriber;
use App\Src\Ports\RadiusClient;
use App\Src\Ports\SubscriberRepository;
use App\Src\Ports\PlanRepository;

/**
 * Provision a subscriber end-to-end (SRD §5.1):
 *   local save -> push to RADIUS (create user, active) -> store mapping.
 * Depends ONLY on ports (Clean/Hexagonal). Never touches HTTP/DB directly.
 */
final class ProvisionSubscriber
{
    public function __construct(
        private SubscriberRepository $subscribers,
        private PlanRepository $plans,
        private RadiusClient $radius,
    ) {}

    /**
     * @param array $data keys: tenant_id, username, password, plan_id, mac?, static_ip?
     */
    public function execute(array $data, string $tenantSlug): Subscriber
    {
        $plan = $this->plans->find((int) $data['plan_id']);
        if (!$plan) {
            throw new \InvalidArgumentException('Unknown plan_id');
        }

        $sub = new Subscriber(
            id: null,
            tenantId: $data['tenant_id'],
            username: $data['username'],
            passwordEnc: self::aesEncrypt($data['password'] ?? ''),
            mac: $data['mac'] ?? null,
            staticIp: $data['static_ip'] ?? null,
            planId: $plan->id,
            status: Subscriber::STATUS_ACTIVE,
        );
        // Compute tenant-namespaced RADIUS username up front (SRD §4.1.1)
        // so the local row satisfies the NOT NULL radius_username column.
        $sub->radiusUsername = $sub->radiusUsername($tenantSlug);
        $sub = $this->subscribers->save($sub);

        // Push to RADIUS with tenant-namespaced username (SRD §4.1.1).
        $created = $this->radius->createUser([
            'username' => $sub->radiusUsername,
            'password' => $data['password'],
            'plan_id' => (int) $plan->radiusProfileId,
            'status' => 'active',
            'mac_lock_enabled' => 1,
            'static_ip' => $data['static_ip'] ?? null,
        ]);
        $sub->radiusUserId = $created['id'] ?? null;
        $this->subscribers->save($sub);

        return $sub;
    }

    /** AES-256-GCM encryption using the app key; placeholder for vault-backed key in prod. */
    private static function aesEncrypt(string $plain): ?string
    {
        if ($plain === '') {
            return null;
        }
        $key = substr(hash('sha256', config('app.key')), 0, 32);
        $iv = random_bytes(12);
        $c = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $c);
    }
}
