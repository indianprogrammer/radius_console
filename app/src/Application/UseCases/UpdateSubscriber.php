<?php

namespace App\Src\Application\UseCases;

use App\Src\Domain\Subscriber;
use App\Src\Ports\BandwidthProfileRepository;
use App\Src\Ports\RadiusClient;
use App\Src\Ports\SubscriberRepository;
use App\Src\Ports\PlanRepository;

/**
 * Update a subscriber end-to-end (SRD §5.1):
 *   fetch RADIUS plan_id -> push to RADIUS (update user) -> sync local record.
 * Depends ONLY on ports (Clean/Hexagonal). Never touches HTTP/DB directly.
 */
final class UpdateSubscriber
{
    public function __construct(
        private SubscriberRepository $subscribers,
        private PlanRepository $plans,
        private BandwidthProfileRepository $profiles,
        private RadiusClient $radius,
    ) {}

    /**
     * @param array $data keys: username?, password?, plan_id?, bandwidth_profile_id?, mac?, static_ip?, expiry?, status?
     */
    public function execute(Subscriber $subscriber, array $data, string $tenantSlug): Subscriber
    {
        // Resolve bandwidth profile (may come from plan link or explicit override).
        $bandwidthProfileId = $data['bandwidth_profile_id'] ?? null;
        if ($bandwidthProfileId === null && !empty($data['plan_id'])) {
            $plan = $this->plans->find((int) $data['plan_id']);
            $bandwidthProfileId = $plan?->bandwidthProfileId;
        }
        $profile = $bandwidthProfileId !== null ? $this->profiles->find((int) $bandwidthProfileId) : null;
        $radiusProfileId = $profile?->radiusPlanId;

        // Update domain entity fields.
        if (isset($data['username'])) {
            $subscriber->username = $data['username'];
            // Keep radiusUsername in sync with new username.
            $subscriber->radiusUsername = $subscriber->radiusUsername($tenantSlug);
        }
        if (array_key_exists('password', $data)) {
            $subscriber->passwordEnc = self::aesEncrypt($data['password']);
        }
        if (array_key_exists('mac', $data)) {
            $subscriber->mac = $data['mac'] ?: null;
        }
        if (array_key_exists('static_ip', $data)) {
            $subscriber->staticIp = $data['static_ip'] ?: null;
        }
        if (array_key_exists('plan_id', $data)) {
            $subscriber->planId = $data['plan_id'] ?: null;
        }
        if (array_key_exists('bandwidth_profile_id', $data)) {
            $subscriber->bandwidthProfileId = $data['bandwidth_profile_id'] ?: null;
        }
        if (array_key_exists('expiry', $data)) {
            $subscriber->expiry = $data['expiry'] ?: null;
        }
        if (array_key_exists('status', $data)) {
            $subscriber->status = $data['status'];
        }

        // Persist locally.
        $subscriber = $this->subscribers->save($subscriber);

        // Sync to RADIUS.
        $payload = [
            'username' => $subscriber->radiusUsername,
            'password' => $data['password'] ?? null,
            'plan_id' => $radiusProfileId,
            'status' => $subscriber->status,
            'mac_lock_enabled' => $subscriber->mac ? 1 : 0,
            'static_ip' => $subscriber->staticIp,
            'expiry_date' => self::toRadiusDateTime($subscriber->expiry),
        ];
        // Only send password if it was changed.
        if (!isset($data['password'])) {
            unset($payload['password']);
        }
        $this->radius->updateUser($subscriber->radiusUserId, $payload);

        return $subscriber;
    }

    /**
     * Normalise an expiry value to the `Y-m-d H:i:s` DATETIME the RADIUS API
     * expects. The form submits `Y-m-d\TH:i`; anything unparseable is dropped
     * rather than sent as an invalid date.
     */
    private static function toRadiusDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
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
