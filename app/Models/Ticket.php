<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Support ticket / work order.
 *
 * Assignment has two levels by design:
 *  - `assigned_staff_id` — the single accountable OWNER.
 *  - `assignees()` — every collaborator, owner included, so a ticket can be
 *    worked by several people or a whole `StaffGroup`.
 *
 * Never write `assigned_staff_id` directly; use `App\Services\TicketAssigner`
 * so the collaborator set and the `ticket_events` trail stay consistent.
 */
class Ticket extends Model
{
    public const CATEGORIES = [
        'installation' => 'New Installation',
        'fault'        => 'Fault / No Internet',
        'slow_speed'   => 'Slow Speed',
        'billing'      => 'Billing Query',
        'complaint'    => 'Complaint',
        'relocation'   => 'Shifting / Relocation',
        'feedback'     => 'Feedback',
        'other'        => 'Other',
    ];

    public const PRIORITIES = [
        'low'    => 'Low',
        'medium' => 'Medium',
        'high'   => 'High',
        'urgent' => 'Urgent',
    ];

    public const STATUSES = [
        'open'        => 'Open',
        'assigned'    => 'Assigned',
        'in_progress' => 'In Progress',
        'on_hold'     => 'On Hold',
        'resolved'    => 'Resolved',
        'closed'      => 'Closed',
        'cancelled'   => 'Cancelled',
    ];

    public const SOURCES = [
        'phone'    => 'Phone',
        'email'    => 'Email',
        'walk_in'  => 'Walk In',
        'app'      => 'Mobile App',
        'web'      => 'Web Portal',
        'whatsapp' => 'WhatsApp',
    ];

    /** Statuses that mean no further work is expected. */
    public const CLOSED_STATUSES = ['resolved', 'closed', 'cancelled'];

    /** SLA target in hours, by priority — drives the default due date. */
    public const SLA_HOURS = [
        'urgent' => 4,
        'high'   => 24,
        'medium' => 48,
        'low'    => 96,
    ];

    protected $fillable = [
        'tenant_id', 'number', 'subject', 'description',
        'category', 'priority', 'status', 'source',
        'subscriber_id', 'franchise_id',
        'assigned_staff_id', 'staff_group_id', 'assigned_at',
        'created_by_staff_id',
        'due_at', 'resolved_at', 'closed_at', 'resolution',
        'contact_name', 'contact_phone', 'address',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_at'      => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at'   => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }

    public function franchise(): BelongsTo { return $this->belongsTo(Franchise::class); }

    /** The accountable owner. */
    public function owner(): BelongsTo { return $this->belongsTo(Staff::class, 'assigned_staff_id'); }

    public function group(): BelongsTo { return $this->belongsTo(StaffGroup::class, 'staff_group_id'); }

    public function creator(): BelongsTo { return $this->belongsTo(Staff::class, 'created_by_staff_id'); }

    /** Every staff member on the ticket (owner + collaborators). */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'ticket_assignees', 'ticket_id', 'staff_id')
            ->withPivot(['is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderByDesc('created_at');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', (string) $this->category));
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst(str_replace('_', ' ', (string) $this->source));
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /** Past its SLA target and still not resolved. */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->isOpen() && $this->due_at->isPast();
    }

    /** Colour bucket for the status pill (reuses the billing pill classes). */
    public function statusPill(): string
    {
        return match ($this->status) {
            'resolved', 'closed' => 'paid',
            'in_progress', 'assigned' => 'partial',
            'cancelled' => 'void',
            'on_hold' => 'overdue',
            default => 'unpaid',
        };
    }

    /**
     * SLA due date for a priority, measured from now.
     *
     * The hours come from Settings > Tickets & SLA when a tenant has configured
     * them; SLA_HOURS is the fallback for unknown priorities and for callers
     * that have no tenant context.
     */
    public static function slaDueAt(string $priority, int|string|null $tenantId = null): \DateTimeInterface
    {
        $fallback = self::SLA_HOURS[$priority] ?? 48;

        if (!array_key_exists($priority, self::SLA_HOURS)) {
            return now()->addHours($fallback);
        }

        $hours = Setting::int("tickets.sla_{$priority}_hours", $tenantId ?? tenant_id());

        return now()->addHours($hours > 0 ? $hours : $fallback);
    }

    /**
     * Next ticket number for the tenant: TKT-000001.
     *
     * MAX() on the numeric tail rather than `orderByDesc('number')` — a string
     * sort would put TKT-000009 after TKT-000010 once the tail widens.
     */
    public static function nextNumber(int|string $tenantId): string
    {
        $last = self::where('tenant_id', $tenantId)
            ->where('number', 'like', 'TKT-%')
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($last && preg_match('/TKT-(\d+)/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('TKT-%06d', $seq);
    }
}
