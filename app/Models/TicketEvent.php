<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable activity row on a ticket: assignment, reassignment, status change
 * or comment. Written only by `App\Services\TicketAssigner` /
 * `TicketController`; never updated or deleted, so the trail can be audited
 * (SRD §9.4 audit log).
 */
class TicketEvent extends Model
{
    public const TYPES = [
        'created'    => 'Created',
        'assigned'   => 'Assigned',
        'reassigned' => 'Reassigned',
        'unassigned' => 'Unassigned',
        'status'     => 'Status Changed',
        'comment'    => 'Comment',
        'resolved'   => 'Resolved',
        'reopened'   => 'Reopened',
    ];

    protected $fillable = [
        'tenant_id', 'ticket_id', 'type',
        'from_staff_id', 'to_staff_id',
        'from_status', 'to_status', 'note', 'actor',
    ];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }

    public function fromStaff(): BelongsTo { return $this->belongsTo(Staff::class, 'from_staff_id'); }

    public function toStaff(): BelongsTo { return $this->belongsTo(Staff::class, 'to_staff_id'); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    /** One-line human summary for the activity timeline. */
    public function summary(): string
    {
        return match ($this->type) {
            'assigned' => 'Assigned to ' . ($this->toStaff->name ?? 'unassigned'),
            'reassigned' => 'Reassigned from ' . ($this->fromStaff->name ?? '—')
                . ' to ' . ($this->toStaff->name ?? '—'),
            'unassigned' => 'Unassigned from ' . ($this->fromStaff->name ?? '—'),
            'status' => 'Status ' . ($this->from_status ?? '—') . ' → ' . ($this->to_status ?? '—'),
            default => $this->typeLabel(),
        };
    }
}
