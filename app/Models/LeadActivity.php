<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable activity row on a lead: a call, a note, a stage change, a hand-off.
 *
 * Same reasoning as `TicketEvent` — the history of contact IS the sales record,
 * so it is appended, never updated. Written only by `LeadService` /
 * `LeadController`.
 */
class LeadActivity extends Model
{
    public const TYPES = [
        'created'   => 'Created',
        'note'      => 'Note',
        'call'      => 'Call',
        'email'     => 'Email',
        'meeting'   => 'Meeting',
        'visit'     => 'Site Visit',
        'status'    => 'Stage Changed',
        'assigned'  => 'Assigned',
        'follow_up' => 'Follow-up Scheduled',
        'quoted'    => 'Quotation Raised',
        'won'       => 'Won',
        'lost'      => 'Lost',
    ];

    /** Types that count as real contact and so refresh `last_contacted_at`. */
    public const CONTACT_TYPES = ['call', 'email', 'meeting', 'visit'];

    protected $fillable = [
        'tenant_id', 'lead_id', 'type',
        'from_staff_id', 'to_staff_id',
        'from_status', 'to_status',
        'note', 'actor', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }

    public function fromStaff(): BelongsTo { return $this->belongsTo(Staff::class, 'from_staff_id'); }

    public function toStaff(): BelongsTo { return $this->belongsTo(Staff::class, 'to_staff_id'); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    /** One-line human summary for the activity timeline. */
    public function summary(): string
    {
        return match ($this->type) {
            'assigned' => 'Assigned to ' . ($this->toStaff->name ?? 'unassigned'),
            'status'   => 'Stage ' . (Lead::STATUSES[$this->from_status] ?? $this->from_status ?? '—')
                . ' → ' . (Lead::STATUSES[$this->to_status] ?? $this->to_status ?? '—'),
            'follow_up' => 'Follow-up scheduled'
                . ($this->occurred_at ? ' for ' . $this->occurred_at->format('d/m/y H:i') : ''),
            default => $this->typeLabel(),
        };
    }

    public function isContact(): bool
    {
        return in_array($this->type, self::CONTACT_TYPES, true);
    }
}