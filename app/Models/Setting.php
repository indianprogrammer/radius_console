<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A tenant-scoped preference, stored key/value (see the settings migration for
 * why it is not a wide table).
 *
 * `SCHEMA` is the single source of truth: it drives the Settings page tabs, the
 * form controls, the validation rules AND the fallback values. Adding a
 * preference is therefore a one-place change — no migration, no view edit.
 *
 * Read values through `Setting::get()` / `Setting::all_for()`; both fall back to
 * the schema default when a tenant has never saved the key.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['tenant_id', 'key', 'value'];

    /**
     * Setting catalogue, grouped into the tabs shown on the Settings page.
     *
     * Field shape:
     *   type    text|number|select|toggle|textarea
     *   default scalar written when nothing is stored
     *   default_config  config() key used as the default instead of `default`,
     *                   for values that also exist as deployment config/env
     *   rules   Laravel validation rules (applied only when the key is posted)
     *   options select choices (value => label)
     *   col     grid width used by the form (matches .field.col-N)
     *
     * A section marked `standalone` is reached from its own menu entry rather
     * than the Settings tab strip (e.g. RADIUS API lives under Radius Control).
     */
    public const SCHEMA = [
        'general' => [
            'label' => 'General',
            'hint'  => 'Company identity used on invoices, the sidebar and outgoing messages.',
            'fields' => [
                'general.company_legal_name' => ['label' => 'Legal Name', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:150', 'col' => 6],
                'general.support_email'      => ['label' => 'Support Email', 'type' => 'text', 'default' => '', 'rules' => 'nullable|email|max:150', 'col' => 3],
                'general.support_phone'      => ['label' => 'Support Phone', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:20', 'col' => 3],
                'general.gst_number'         => ['label' => 'GST Number', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:20', 'col' => 3],
                'general.pan_number'         => ['label' => 'PAN Number', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:20', 'col' => 3],
                'general.address'            => ['label' => 'Registered Address', 'type' => 'textarea', 'default' => '', 'rules' => 'nullable|string|max:500', 'col' => 6],
            ],
        ],

        'localization' => [
            'label' => 'Localization',
            'hint'  => 'Currency, timezone and date formatting for the whole console.',
            'fields' => [
                'localization.currency' => [
                    'label' => 'Currency', 'type' => 'select', 'default' => 'INR', 'rules' => 'nullable|in:INR,USD,EUR,GBP,AED', 'col' => 3,
                    'options' => ['INR' => 'INR — ₹', 'USD' => 'USD — $', 'EUR' => 'EUR — €', 'GBP' => 'GBP — £', 'AED' => 'AED — د.إ'],
                ],
                'localization.timezone' => [
                    'label' => 'Timezone', 'type' => 'select', 'default' => 'Asia/Kolkata', 'rules' => 'nullable|timezone', 'col' => 3,
                    'options' => [
                        'Asia/Kolkata' => 'Asia/Kolkata', 'Asia/Dubai' => 'Asia/Dubai', 'UTC' => 'UTC',
                        'Europe/London' => 'Europe/London', 'America/New_York' => 'America/New_York',
                    ],
                ],
                'localization.date_format' => [
                    'label' => 'Date Format', 'type' => 'select', 'default' => 'd/m/Y', 'rules' => 'nullable|in:d/m/Y,m/d/Y,Y-m-d', 'col' => 3,
                    'options' => ['d/m/Y' => 'dd/mm/yyyy', 'm/d/Y' => 'mm/dd/yyyy', 'Y-m-d' => 'yyyy-mm-dd'],
                ],
                'localization.financial_year_start' => [
                    'label' => 'Financial Year Starts', 'type' => 'select', 'default' => '4', 'rules' => 'nullable|integer|between:1,12', 'col' => 3,
                    'options' => [
                        '1' => 'January', '2' => 'February', '3' => 'March', '4' => 'April', '5' => 'May', '6' => 'June',
                        '7' => 'July', '8' => 'August', '9' => 'September', '10' => 'October', '11' => 'November', '12' => 'December',
                    ],
                ],
            ],
        ],

        'billing' => [
            'label' => 'Billing',
            'hint'  => 'Defaults applied when invoices are generated. Existing invoices are untouched.',
            'fields' => [
                'billing.invoice_prefix'   => ['label' => 'Invoice Prefix', 'type' => 'text', 'default' => 'INV', 'rules' => 'nullable|string|max:10', 'col' => 3, 'hint' => 'Numbers read PREFIX-YYMM-0001.'],
                'billing.invoice_due_days' => ['label' => 'Payment Terms (days)', 'type' => 'number', 'default' => '15', 'rules' => 'nullable|integer|between:0,365', 'col' => 3],
                'billing.grace_period_days' => ['label' => 'Grace Period (days)', 'type' => 'number', 'default' => '3', 'rules' => 'nullable|integer|between:0,90', 'col' => 3, 'hint' => 'Days past expiry before suspension.'],
                'billing.round_invoice_total' => ['label' => 'Round Invoice Total Up', 'type' => 'toggle', 'default' => '1', 'rules' => 'nullable|boolean', 'col' => 3],
                'billing.auto_generate_invoice' => ['label' => 'Auto-generate on Renewal', 'type' => 'toggle', 'default' => '1', 'rules' => 'nullable|boolean', 'col' => 3],
                'billing.invoice_footer' => ['label' => 'Invoice Footer Note', 'type' => 'textarea', 'default' => '', 'rules' => 'nullable|string|max:500', 'col' => 6],
            ],
        ],

        'tickets' => [
            'label' => 'Tickets & SLA',
            'hint'  => 'Helpdesk defaults. SLA hours seed a ticket\'s due date from its priority.',
            'fields' => [
                'tickets.default_priority' => [
                    'label' => 'Default Priority', 'type' => 'select', 'default' => 'medium', 'rules' => 'nullable|in:low,medium,high,urgent', 'col' => 3,
                    'options' => Ticket::PRIORITIES,
                ],
                'tickets.sla_urgent_hours' => ['label' => 'SLA — Urgent (hrs)', 'type' => 'number', 'default' => '4', 'rules' => 'nullable|integer|between:1,720', 'col' => 3],
                'tickets.sla_high_hours'   => ['label' => 'SLA — High (hrs)', 'type' => 'number', 'default' => '24', 'rules' => 'nullable|integer|between:1,720', 'col' => 3],
                'tickets.sla_medium_hours' => ['label' => 'SLA — Medium (hrs)', 'type' => 'number', 'default' => '48', 'rules' => 'nullable|integer|between:1,720', 'col' => 3],
                'tickets.sla_low_hours'    => ['label' => 'SLA — Low (hrs)', 'type' => 'number', 'default' => '96', 'rules' => 'nullable|integer|between:1,720', 'col' => 3],
                'tickets.require_resolution_on_close' => ['label' => 'Require Resolution to Close', 'type' => 'toggle', 'default' => '1', 'rules' => 'nullable|boolean', 'col' => 3],
                'tickets.auto_close_resolved_days' => ['label' => 'Auto-close Resolved After (days)', 'type' => 'number', 'default' => '7', 'rules' => 'nullable|integer|between:0,365', 'col' => 3, 'hint' => '0 disables auto-close.'],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'hint'  => 'Channels used for expiry reminders, invoices and ticket updates.',
            'fields' => [
                'notifications.email_enabled'    => ['label' => 'Email', 'type' => 'toggle', 'default' => '1', 'rules' => 'nullable|boolean', 'col' => 3],
                'notifications.sms_enabled'      => ['label' => 'SMS', 'type' => 'toggle', 'default' => '0', 'rules' => 'nullable|boolean', 'col' => 3],
                'notifications.whatsapp_enabled' => ['label' => 'WhatsApp', 'type' => 'toggle', 'default' => '0', 'rules' => 'nullable|boolean', 'col' => 3],
                'notifications.expiry_reminder_days' => ['label' => 'Expiry Reminder (days before)', 'type' => 'text', 'default' => '7,3,1', 'rules' => 'nullable|string|max:50', 'col' => 3, 'hint' => 'Comma-separated day offsets.'],
                'notifications.sender_name'  => ['label' => 'Sender Name', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:100', 'col' => 3],
                'notifications.reply_to'     => ['label' => 'Reply-To Address', 'type' => 'text', 'default' => '', 'rules' => 'nullable|email|max:150', 'col' => 3],
            ],
        ],

        'subscribers' => [
            'label' => 'Subscribers',
            'hint'  => 'Defaults applied by the subscriber onboarding form.',
            'fields' => [
                'subscribers.default_status' => [
                    'label' => 'Default Status', 'type' => 'select', 'default' => 'active', 'rules' => 'nullable|in:active,suspended,expired', 'col' => 3,
                    'options' => ['active' => 'Active', 'suspended' => 'Suspended', 'expired' => 'Expired'],
                ],
                'subscribers.auto_renew_default' => ['label' => 'Auto Renew by Default', 'type' => 'toggle', 'default' => '0', 'rules' => 'nullable|boolean', 'col' => 3],
                'subscribers.require_kyc'        => ['label' => 'Require KYC Before Activation', 'type' => 'toggle', 'default' => '0', 'rules' => 'nullable|boolean', 'col' => 3],
                'subscribers.username_prefix'    => ['label' => 'Username Prefix', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:20', 'col' => 3, 'hint' => 'Prepended to new RADIUS usernames.'],
            ],
        ],

        // Reached from Radius Control > RADIUS API, not the Settings tab strip.
        'radius' => [
            'label' => 'RADIUS API',
            'standalone' => true,
            'hint'  => 'Base URL of the external RADIUS management server. Overrides the RADIUS_API_BASE environment value; leave blank to fall back to it.',
            'fields' => [
                'radius.api_base_url' => [
                    'label' => 'RADIUS Server URL',
                    'type'  => 'text',
                    // Falls back to config/radius.php (RADIUS_API_BASE) so the
                    // box always shows the URL actually in effect.
                    'default' => '',
                    'default_config' => 'radius.base_url',
                    'rules' => 'nullable|url|max:255',
                    'col'   => 12,
                    'hint'  => 'Include the scheme and the /api path, e.g. http://127.0.0.1:8001/api',
                ],
            ],
        ],
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    /** Flat map of key => field definition across every section. */
    public static function fields(): array
    {
        $out = [];
        foreach (self::SCHEMA as $section) {
            foreach ($section['fields'] as $key => $def) {
                $out[$key] = $def;
            }
        }
        return $out;
    }

    /** Schema default for a key (empty string when the key is unknown). */
    public static function defaultFor(string $key): string
    {
        $def = self::fields()[$key] ?? null;
        if ($def === null) {
            return '';
        }

        if (isset($def['default_config'])) {
            return (string) config($def['default_config'], $def['default'] ?? '');
        }

        return (string) ($def['default'] ?? '');
    }

    /**
     * Form-safe form of a key: "billing.invoice_prefix" => "billing__invoice_prefix".
     *
     * PHP converts `.` in request keys to `_`, and Laravel treats `.` as nested
     * array notation in validation rules — so the dotted key never survives a
     * round trip through a form. Inputs are named `settings[<safe key>]`.
     */
    public static function safeKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * Every effective value for a tenant: schema defaults overlaid with the
     * stored rows. Cached per tenant so a page render is one query.
     */
    public static function all_for(int|string $tenantId): array
    {
        return Cache::remember(self::cacheKey($tenantId), 300, function () use ($tenantId) {
            $values = [];
            foreach (array_keys(self::fields()) as $key) {
                $values[$key] = self::defaultFor($key);
            }

            foreach (self::where('tenant_id', $tenantId)->get() as $row) {
                // Ignore rows for keys retired from the schema.
                if (array_key_exists($row->key, $values)) {
                    $values[$row->key] = (string) $row->value;
                }
            }

            return $values;
        });
    }

    /** Effective value for one key. */
    public static function get(string $key, int|string|null $tenantId = null): string
    {
        $tenantId = $tenantId ?? tenant_id();
        return self::all_for($tenantId)[$key] ?? self::defaultFor($key);
    }

    /** Effective value coerced to bool — for the `toggle` fields. */
    public static function bool(string $key, int|string|null $tenantId = null): bool
    {
        return filter_var(self::get($key, $tenantId), FILTER_VALIDATE_BOOLEAN);
    }

    /** Effective value coerced to int — for the `number` fields. */
    public static function int(string $key, int|string|null $tenantId = null): int
    {
        return (int) self::get($key, $tenantId);
    }

    /** Upsert one key and drop the tenant's cache. */
    public static function put(string $key, ?string $value, int|string|null $tenantId = null): void
    {
        $tenantId = $tenantId ?? tenant_id();

        self::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $value],
        );

        self::forget($tenantId);
    }

    public static function forget(int|string $tenantId): void
    {
        Cache::forget(self::cacheKey($tenantId));
    }

    /**
     * Effective RADIUS API base URL for a tenant.
     *
     * The stored setting wins; when unset, `defaultFor()` resolves the key's
     * `default_config` (config/radius.php → RADIUS_API_BASE) so an
     * unconfigured tenant keeps the deployment default. Any trailing slash is
     * dropped because callers concatenate paths as `$base . '/auth/login'`.
     *
     * Wrapped in a try/catch: this is called from the RADIUS adapter, which can
     * run in console/queue contexts with no tenant resolved or before the
     * settings table exists. Configuration must never be a hard dependency of
     * being able to talk to RADIUS.
     */
    public static function radiusBaseUrl(int|string|null $tenantId = null): string
    {
        $fallback = (string) config('radius.base_url', 'http://127.0.0.1:8001/api');

        try {
            $url = trim(self::get('radius.api_base_url', $tenantId));
        } catch (\Throwable) {
            $url = '';
        }

        return rtrim($url !== '' ? $url : $fallback, '/');
    }

    private static function cacheKey(int|string $tenantId): string
    {
        return "settings.tenant.{$tenantId}";
    }
}
