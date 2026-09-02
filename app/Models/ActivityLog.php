<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row in the Logs menu — an immutable record of something that happened.
 *
 * Backed by `audit_log` (the SRD §8 entity) rather than a new table, widened by
 * `2026_09_02_000100_extend_audit_log_for_logs_menu`. Every log page in the menu
 * is this model filtered by `channel`, because Audit Logs, Login History, SMS
 * Logs and the rest are the same shape and differ only in origin.
 *
 * Append-only by contract: there is no update path anywhere in the app, and the
 * object is stored as loose `object_type`/`object_id` strings instead of an FK
 * precisely so deleting the subject cannot erase its history.
 *
 * Write through `App\Services\ActivityLogger`, never directly — it is what
 * stamps the tenant, the actor and the request context, and what redacts
 * secrets before they can reach a log (SRD §9.4).
 */
class ActivityLog extends Model
{
    /** The SRD names this entity `audit_log`; Eloquent would guess "activity_logs". */
    protected $table = 'audit_log';

    /**
     * The Logs menu, in menu order. Key = stored `channel`, value = page label.
     *
     * Adding a channel is a one-line change here plus a menu entry — no
     * migration, because `channel` is a plain string column.
     */
    public const CHANNELS = [
        'audit'      => 'Audit Logs',
        'login'      => 'Login History',
        'login_fail' => 'Login Fail Attempts',
        'sms'        => 'SMS Logs',
        'email'      => 'Email Logs',
        'call'       => 'Call Logs',
        'whatsapp'   => 'WhatsApp Logs',
        'aadhaar'    => 'Aadhaar Logs',
        'syslog'     => 'User Syslogs',
    ];

    /**
     * Channels that are outbound message deliveries. They share a "recipient +
     * delivery outcome" reading, so the listing renders them with a different
     * column set than an audit entry.
     */
    public const MESSAGE_CHANNELS = ['sms', 'email', 'call', 'whatsapp'];

    /** Channels describing an authentication attempt. */
    public const AUTH_CHANNELS = ['login', 'login_fail'];

    public const STATUSES = [
        'success' => 'Success',
        'failed'  => 'Failed',
        'pending' => 'Pending',
    ];

    /**
     * Verbs used by the app, offered as a filter. Free-form on write — a log
     * must be able to record an action nobody predicted.
     */
    public const ACTIONS = [
        'created'      => 'Created',
        'updated'      => 'Updated',
        'deleted'      => 'Deleted',
        'login'        => 'Login',
        'login_failed' => 'Login Failed',
        'logout'       => 'Logout',
        'sent'         => 'Sent',
        'delivered'    => 'Delivered',
        'failed'       => 'Failed',
        'verified'     => 'Verified',
        'rejected'     => 'Rejected',
        'provisioned'  => 'Provisioned',
        'suspended'    => 'Suspended',
        'disconnected' => 'Disconnected',
    ];

    protected $fillable = [
        'tenant_id', 'channel', 'user_id', 'actor',
        'action', 'object_type', 'object_id', 'object_label',
        'status', 'message', 'ip_address', 'user_agent', 'payload',
    ];

    protected $casts = [
        // The legacy column is TEXT; casting to array keeps callers free to
        // hand over a structured context without serialising by hand.
        'payload' => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    // ---- Scopes -----------------------------------------------------------

    public function scopeChannel(Builder $query, string $channel): void
    {
        $query->where('channel', $channel);
    }

    /**
     * Free-text search across the human-readable columns.
     *
     * `payload` is deliberately excluded: it is JSON in a TEXT column, so a LIKE
     * over it matches key names and escaped punctuation and mostly returns noise.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (($term = trim((string) $term)) === '') {
            return;
        }

        $query->where(function (Builder $w) use ($term) {
            foreach (['actor', 'action', 'object_type', 'object_label', 'message', 'ip_address'] as $column) {
                $w->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    // ---- Display ----------------------------------------------------------

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? ucfirst(str_replace('_', ' ', (string) $this->channel));
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace('_', ' ', (string) $this->action));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Colour bucket for the status pill (reuses the shared billing pills). */
    public function statusPill(): string
    {
        return match ($this->status) {
            'failed'  => 'overdue',
            'pending' => 'partial',
            default   => 'completed',
        };
    }

    public function isFailure(): bool
    {
        return $this->status === 'failed';
    }

    /** "Subscriber #12 (john.doe)" — what the action was performed on. */
    public function objectSummary(): string
    {
        if (!$this->object_type && !$this->object_label) {
            return '—';
        }

        $type = $this->object_type ? class_basename($this->object_type) : 'Record';
        $ref  = $this->object_id ? " #{$this->object_id}" : '';

        return $this->object_label ? "{$type}{$ref} ({$this->object_label})" : $type . $ref;
    }

    /**
     * Payload rendered for the detail drawer. Pretty-printed rather than dumped
     * so a nested context stays readable; empty payloads render nothing.
     */
    public function payloadJson(): ?string
    {
        $payload = $this->payload;

        if (empty($payload)) {
            return null;
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function isMessageChannel(): bool
    {
        return in_array($this->channel, self::MESSAGE_CHANNELS, true);
    }

    public function isAuthChannel(): bool
    {
        return in_array($this->channel, self::AUTH_CHANNELS, true);
    }
}
