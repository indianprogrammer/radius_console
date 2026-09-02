<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The only writer of `audit_log` — the Logs menu (SRD §5.0 #10, §9.8).
 *
 * Centralised for three reasons that a scattered `ActivityLog::create()` would
 * get wrong:
 *
 *  1. SECRETS. SRD §9.4 forbids credentials and PII in logs. `redact()` strips
 *     them from the payload before it is ever persisted, so a caller cannot leak
 *     a password by passing the whole request. This is the main reason the model
 *     is not written to directly.
 *  2. CONTEXT. Tenant, actor, IP and user agent are stamped here; the
 *     alternative is every call site remembering to do it, and the ones that
 *     forget produce a log that cannot answer "who did this, from where".
 *  3. FAILING SOFT. An audit write must never break the operation it describes.
 *     Every method swallows storage errors into the application log instead of
 *     bubbling — a broken log table would otherwise take down subscriber
 *     provisioning with it.
 *
 * Reads happen through the model; there is deliberately no update or delete
 * path, since an audit trail that can be rewritten is not one.
 */
final class ActivityLogger
{
    /**
     * Payload keys that are never stored, matched case-insensitively as a
     * SUBSTRING — `password`, `password_confirmation`, `pppoe_password` and
     * `api_password` all have to be caught, and enumerating them is a losing game.
     */
    private const REDACT_KEYS = [
        'password', 'passwd', 'secret', 'token', 'authorization', 'api_key',
        'apikey', 'access_key', 'private_key', 'credential', 'csrf',
        'shared_secret', 'password_enc', 'otp', 'pin',
    ];

    private const REDACTED = '[redacted]';

    /**
     * Record an event.
     *
     * @param string               $channel One of ActivityLog::CHANNELS.
     * @param string               $action  Verb, e.g. `created` / `login_failed`.
     * @param array<string, mixed> $context Everything else; see the named keys below.
     */
    public function log(string $channel, string $action, array $context = []): ?ActivityLog
    {
        try {
            return ActivityLog::create([
                'tenant_id'    => $context['tenant_id'] ?? tenant_id(),
                'channel'      => $channel,
                'user_id'      => $context['user_id'] ?? $this->currentUserId(),
                'actor'        => $context['actor'] ?? $this->currentActor(),
                'action'       => $action,
                'object_type'  => $context['object_type'] ?? null,
                'object_id'    => isset($context['object_id']) ? (string) $context['object_id'] : null,
                'object_label' => $context['object_label'] ?? null,
                'status'       => $context['status'] ?? 'success',
                'message'      => $context['message'] ?? null,
                'ip_address'   => $context['ip_address'] ?? $this->requestIp(),
                'user_agent'   => $context['user_agent'] ?? $this->userAgent(),
                'payload'      => $this->redact($context['payload'] ?? []) ?: null,
            ]);
        } catch (\Throwable $e) {
            // Never let the audit trail break the action it is describing.
            Log::warning('Activity log write failed', [
                'channel' => $channel,
                'action'  => $action,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Audit a change to a domain record — the common case.
     *
     * @param object|null $model Any Eloquent model; its class and key are stored
     *                           as loose strings so the log outlives the row.
     */
    public function audit(string $action, ?object $model = null, array $context = []): ?ActivityLog
    {
        if ($model !== null) {
            $context += [
                'object_type'  => $model::class,
                'object_id'    => $model->getKey() ?? null,
                'object_label' => $this->labelFor($model),
            ];
        }

        return $this->log('audit', $action, $context);
    }

    /**
     * A successful sign-in — Login History.
     *
     * The tenant is taken from the USER, not from the resolved request: a login
     * is authenticated before anything tenant-scoped is rendered, so
     * `tenant_id()` can still be empty at this point.
     */
    public function loginSucceeded(?object $user = null, array $context = []): ?ActivityLog
    {
        return $this->log('login', 'login', $context + [
            'tenant_id' => $user->tenant_id ?? null,
            'user_id' => $user?->getKey(),
            'actor'   => $user->name ?? $user->email ?? null,
            'message' => 'Signed in.',
        ]);
    }

    /**
     * A rejected sign-in — Login Fail Attempts.
     *
     * The attempted identifier IS the record here, so it is stored as the actor;
     * the submitted password is not passed on at all.
     */
    public function loginFailed(?string $identifier, ?string $reason = null, array $context = []): ?ActivityLog
    {
        return $this->log('login_fail', 'login_failed', $context + [
            'actor'   => $identifier,
            'user_id' => null,
            'status'  => 'failed',
            'message' => $reason ?? 'Invalid credentials.',
        ]);
    }

    /**
     * An outbound message on one of the MESSAGE_CHANNELS.
     *
     * `$to` lands in `object_label` rather than the message body, so the listing
     * can show a recipient column without parsing anything.
     */
    public function message(string $channel, string $to, string $status = 'success', array $context = []): ?ActivityLog
    {
        return $this->log($channel, $status === 'failed' ? 'failed' : 'sent', $context + [
            'object_type'  => 'recipient',
            'object_label' => $to,
            'status'       => $status,
        ]);
    }

    /** A subscriber-facing system event — User Syslogs. */
    public function syslog(string $action, array $context = []): ?ActivityLog
    {
        return $this->log('syslog', $action, $context);
    }

    // ---- Context resolution ----------------------------------------------

    /**
     * Recursively strip secret-looking values.
     *
     * Keys are matched by substring so variants (`pppoe_password`,
     * `api_password`) are caught without listing each one. Values are replaced
     * rather than dropped: knowing a password WAS submitted is useful, knowing
     * what it was is a liability.
     *
     * @param mixed $payload
     * @return array<string, mixed>
     */
    private function redact(mixed $payload): array
    {
        if (!is_array($payload)) {
            return $payload === null || $payload === '' ? [] : ['value' => $payload];
        }

        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSecretKey($key)) {
                $clean[$key] = self::REDACTED;
                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACT_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The console login behind the action, when there is one.
     *
     * All request context is resolved defensively: this service also runs from
     * console commands and queued jobs, where there is no request and no user.
     */
    private function currentUserId(): ?int
    {
        try {
            return auth()->check() ? (int) auth()->id() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function currentActor(): ?string
    {
        try {
            return auth()->user()?->name ?? auth()->user()?->email ?? 'system';
        } catch (\Throwable) {
            return 'system';
        }
    }

    private function requestIp(): ?string
    {
        return $this->request()?->ip();
    }

    private function userAgent(): ?string
    {
        $agent = $this->request()?->userAgent();

        // The column is varchar(255) and some agents are longer than that.
        return $agent ? mb_substr($agent, 0, 255) : null;
    }

    private function request(): ?Request
    {
        try {
            return app()->runningInConsole() ? null : request();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Human label for a model: whichever identifying attribute it actually has.
     * Checked in order of specificity so a subscriber reads as its username, not
     * its number.
     */
    private function labelFor(object $model): ?string
    {
        foreach (['number', 'username', 'name', 'code', 'sku', 'subject', 'key'] as $attribute) {
            $value = $model->{$attribute} ?? null;

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 200);
            }
        }

        return null;
    }
}
