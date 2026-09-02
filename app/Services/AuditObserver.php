<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Feeds the Audit Logs page from Eloquent model events.
 *
 * Registered in `AppServiceProvider` against an explicit list of auditable
 * models (see `AUDITED`). Deliberately an observer rather than a call in each
 * controller: SRD §9.8 requires the audit trail to cover ALL provisioning and
 * control actions, and twenty-odd controllers each remembering to log is the
 * arrangement where a few quietly do not. Bulk queries still bypass this —
 * `Model::where(...)->update()` fires no events — but every CRUD screen in the
 * console goes through model instances.
 *
 * The trail records WHAT changed, never the new values wholesale: `updated`
 * stores the changed attribute NAMES only. Storing the values would copy
 * password hashes, encrypted credentials and PII into a second table with a
 * longer retention than the record itself.
 */
final class AuditObserver
{
    /**
     * Models whose lifecycle is worth auditing — the entities that have a CRUD
     * screen. An allow-list, not a deny-list: a global observer would also log
     * cache/session rows and every immutable trail row (`lead_activities`,
     * `ticket_events`), which are already history.
     *
     * `ActivityLog` itself is absent for the obvious reason — auditing the audit
     * writer recurses. `Tenant` is absent because it is the root of the
     * hierarchy and has no `tenant_id` to scope the entry to. `Setting` is
     * absent because it is written in a loop on every settings save, which would
     * bury real activity under dozens of rows.
     */
    public const AUDITED = [
        \App\Models\Subscriber::class,
        \App\Models\Plan::class,
        \App\Models\BandwidthProfile::class,
        \App\Models\Nas::class,
        \App\Models\TaxRate::class,
        \App\Models\Product::class,
        \App\Models\Inventory::class,
        \App\Models\Invoice::class,
        \App\Models\Payment::class,
        \App\Models\Quote::class,
        \App\Models\Franchise::class,
        \App\Models\Staff::class,
        \App\Models\StaffGroup::class,
        \App\Models\Ticket::class,
        \App\Models\Lead::class,
        \App\Models\Payslip::class,
    ];

    /**
     * Attributes that are noise in a change list: touched by the framework or by
     * a derived recalculation, not by a person.
     */
    private const IGNORED_ATTRIBUTES = ['updated_at', 'created_at', 'remember_token'];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->record('created', $model);
    }

    public function updated(Model $model): void
    {
        $changed = array_values(array_diff(
            array_keys($model->getChanges()),
            self::IGNORED_ATTRIBUTES,
        ));

        // A save that only bumped `updated_at` is not a change anyone made.
        if ($changed === []) {
            return;
        }

        $this->record('updated', $model, [
            'message' => 'Changed: ' . implode(', ', $changed),
            'payload' => ['changed' => $changed],
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model);
    }

    /**
     * Write the entry, scoped to the model's OWN tenant.
     *
     * Taken from the model rather than `tenant_id()` so a record touched by a
     * console command or a queued job — where no request resolved a tenant —
     * still lands in the right tenant's log instead of failing the FK.
     * `bandwidth_profiles` names the column `company_id`, hence the fallback.
     */
    private function record(string $action, Model $model, array $context = []): void
    {
        $tenantId = $model->getAttribute('tenant_id') ?? $model->getAttribute('company_id');

        if (!$tenantId) {
            return;
        }

        $this->logger->audit($action, $model, $context + ['tenant_id' => $tenantId]);
    }

    /** Register this observer for every audited model. */
    public static function register(): void
    {
        foreach (self::AUDITED as $model) {
            if (class_exists($model)) {
                $model::observe(self::class);
            }
        }
    }
}
