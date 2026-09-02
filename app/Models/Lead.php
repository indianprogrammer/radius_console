<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales lead / prospect — Sales.
 *
 * NOT a subscriber: a lead has no RADIUS account, no plan obligation and no
 * billing identity, and most never convert. See the leads migration for why the
 * two are separate tables.
 *
 * The pipeline is a straight line of statuses (`STATUSES`); `won` is only
 * reached through `LeadService::markWon()` so the timestamps, the trail and the
 * subscriber link can never disagree.
 */
class Lead extends Model
{
    public const SOURCES = [
        'walk_in'  => 'Walk In',
        'phone'    => 'Phone',
        'website'  => 'Website',
        'referral' => 'Referral',
        'campaign' => 'Campaign',
        'social'   => 'Social Media',
        'partner'  => 'Partner / LCO',
        'other'    => 'Other',
    ];

    /** Pipeline stages, in order. `ORDERED_STAGES` drives the funnel display. */
    public const STATUSES = [
        'new'         => 'New',
        'contacted'   => 'Contacted',
        'qualified'   => 'Qualified',
        'proposal'    => 'Proposal Sent',
        'negotiation' => 'Negotiation',
        'won'         => 'Won',
        'lost'        => 'Lost',
    ];

    /** Open stages in pipeline order, excluding the two terminal ones. */
    public const ORDERED_STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation'];

    /** Statuses that mean the lead is closed — no further sales work. */
    public const CLOSED_STATUSES = ['won', 'lost'];

    public const RATINGS = [
        'hot'  => 'Hot',
        'warm' => 'Warm',
        'cold' => 'Cold',
    ];

    protected $fillable = [
        'tenant_id', 'number', 'name', 'company',
        'email', 'phone', 'alternate_phone',
        'address', 'city', 'state', 'pincode',
        'source', 'status', 'rating',
        'plan_id', 'estimated_value',
        'assigned_staff_id', 'franchise_id',
        'next_follow_up_at', 'last_contacted_at',
        'won_at', 'lost_at', 'lost_reason',
        'quote_id', 'subscriber_id',
        'notes',
    ];

    protected $casts = [
        'estimated_value'   => 'float',
        'next_follow_up_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'won_at'            => 'datetime',
        'lost_at'           => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }

    public function owner(): BelongsTo { return $this->belongsTo(Staff::class, 'assigned_staff_id'); }

    public function franchise(): BelongsTo { return $this->belongsTo(Franchise::class); }

    /** The quotation raised for this lead, if any. */
    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }

    /** The subscriber this lead became, once won and onboarded. */
    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst(str_replace('_', ' ', (string) $this->source));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function ratingLabel(): string
    {
        return self::RATINGS[$this->rating] ?? ucfirst((string) $this->rating);
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isWon(): bool
    {
        return $this->status === 'won';
    }

    /** A closed lead is read-only apart from its notes and trail. */
    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /** Follow-up date has passed and the lead is still open. */
    public function isFollowUpDue(): bool
    {
        return $this->next_follow_up_at !== null
            && $this->isOpen()
            && $this->next_follow_up_at->isPast();
    }

    /** Display name: company disambiguates two prospects with the same name. */
    public function displayName(): string
    {
        return $this->company ? "{$this->name} ({$this->company})" : $this->name;
    }

    /** Colour bucket for the status pill (reuses the shared billing pills). */
    public function statusPill(): string
    {
        return match ($this->status) {
            'won'                       => 'paid',
            'qualified', 'negotiation'  => 'partial',
            'proposal'                  => 'info',
            'lost'                      => 'void',
            'contacted'                 => 'pending',
            default                     => 'draft',
        };
    }

    public function ratingPill(): string
    {
        return match ($this->rating) {
            'hot'  => 'overdue',
            'cold' => 'draft',
            default => 'info',
        };
    }

    /**
     * Next number for the tenant: LEAD-000001.
     *
     * Sorted by id and parsed, not by the string `number` — a string sort puts
     * LEAD-000009 after LEAD-000010 once the tail widens.
     */
    public static function nextNumber(int|string $tenantId): string
    {
        $last = self::where('tenant_id', $tenantId)
            ->where('number', 'like', 'LEAD-%')
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($last && preg_match('/LEAD-(\d+)/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('LEAD-%06d', $seq);
    }
}