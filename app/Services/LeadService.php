<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\DB;

/**
 * The single place that moves a lead through its pipeline.
 *
 * Rules live here rather than in the controller so the trail, the timestamps
 * and the hand-off links can never disagree:
 *  1. Every stage change appends a `lead_activities` row (from → to).
 *  2. `won` / `lost` stamp their timestamp exactly once and clear the pending
 *     follow-up — a closed lead must not sit in the follow-up queue.
 *  3. Contact activities (call/email/meeting/visit) refresh `last_contacted_at`,
 *     and a `new` lead advances to `contacted` on first real contact.
 *  4. Raising a quotation is the only way `leads.quote_id` is set, and it moves
 *     the lead to `proposal` so the pipeline reflects reality.
 */
final class LeadService
{
    /**
     * Append an activity, updating the derived lead columns it implies.
     *
     * @param array{note?:string|null,occurred_at?:mixed,to_staff_id?:int|null} $extra
     */
    public function logActivity(Lead $lead, string $type, array $extra = [], ?string $actor = null): LeadActivity
    {
        return DB::transaction(function () use ($lead, $type, $extra, $actor) {
            $activity = LeadActivity::create([
                'tenant_id'     => $lead->tenant_id,
                'lead_id'       => $lead->id,
                'type'          => $type,
                'note'          => $extra['note'] ?? null,
                'to_staff_id'   => $extra['to_staff_id'] ?? null,
                'from_staff_id' => $extra['from_staff_id'] ?? null,
                'occurred_at'   => $extra['occurred_at'] ?? now(),
                'actor'         => $actor,
            ]);

            if ($activity->isContact()) {
                $lead->last_contacted_at = $activity->occurred_at ?? now();

                // First real contact advances the stage; later contacts do not
                // reopen a lead that has already progressed or closed.
                if ($lead->status === 'new') {
                    $this->applyStatus($lead, 'contacted', $actor);
                }

                $lead->save();
            }

            return $activity;
        });
    }

    /**
     * Move the lead to a new stage, recording the transition.
     *
     * `won` and `lost` route through markWon()/markLost() so their side effects
     * are never skipped by a plain status edit.
     */
    public function changeStatus(Lead $lead, string $status, ?string $note = null, ?string $actor = null): Lead
    {
        if ($status === $lead->status) {
            return $lead;
        }

        return match ($status) {
            'won'   => $this->markWon($lead, $note, $actor),
            'lost'  => $this->markLost($lead, $note, $actor),
            default => DB::transaction(function () use ($lead, $status, $note, $actor) {
                $this->applyStatus($lead, $status, $actor, $note);
                // Reopening a closed lead clears its closure stamps, or the
                // funnel would keep counting it as won/lost.
                $lead->won_at = null;
                $lead->lost_at = null;
                $lead->lost_reason = null;
                $lead->save();

                return $lead->refresh();
            }),
        };
    }

    public function markWon(Lead $lead, ?string $note = null, ?string $actor = null): Lead
    {
        return DB::transaction(function () use ($lead, $note, $actor) {
            $this->applyStatus($lead, 'won', $actor, $note);

            $lead->won_at = $lead->won_at ?? now();
            $lead->lost_at = null;
            $lead->lost_reason = null;
            // A closed lead must leave the follow-up queue.
            $lead->next_follow_up_at = null;
            $lead->save();

            LeadActivity::create([
                'tenant_id'   => $lead->tenant_id,
                'lead_id'     => $lead->id,
                'type'        => 'won',
                'note'        => $note,
                'occurred_at' => now(),
                'actor'       => $actor,
            ]);

            return $lead->refresh();
        });
    }

    public function markLost(Lead $lead, ?string $reason = null, ?string $actor = null): Lead
    {
        return DB::transaction(function () use ($lead, $reason, $actor) {
            $this->applyStatus($lead, 'lost', $actor, $reason);

            $lead->lost_at = $lead->lost_at ?? now();
            $lead->lost_reason = $reason;
            $lead->won_at = null;
            $lead->next_follow_up_at = null;
            $lead->save();

            LeadActivity::create([
                'tenant_id'   => $lead->tenant_id,
                'lead_id'     => $lead->id,
                'type'        => 'lost',
                'note'        => $reason,
                'occurred_at' => now(),
                'actor'       => $actor,
            ]);

            return $lead->refresh();
        });
    }

    /**
     * Schedule the next follow-up. Kept separate from a general update so the
     * queue always has a matching trail entry explaining who set it.
     */
    public function scheduleFollowUp(Lead $lead, \DateTimeInterface $when, ?string $note = null, ?string $actor = null): Lead
    {
        return DB::transaction(function () use ($lead, $when, $note, $actor) {
            $lead->next_follow_up_at = $when;
            $lead->save();

            LeadActivity::create([
                'tenant_id'   => $lead->tenant_id,
                'lead_id'     => $lead->id,
                'type'        => 'follow_up',
                'note'        => $note,
                'occurred_at' => $when,
                'actor'       => $actor,
            ]);

            return $lead->refresh();
        });
    }

    /**
     * Raise a quotation for the lead and link the two.
     *
     * The lead's own plan/estimated value seeds a single line item, so the
     * salesperson gets a document to edit rather than an empty form. The
     * quotation is the existing pre-sale surface — nothing about pricing,
     * numbering or conversion is duplicated here.
     *
     * @throws \RuntimeException when the lead already has a quotation or is closed.
     */
    public function createQuotation(Lead $lead, ?string $actor = null): Quote
    {
        if ($lead->quote_id !== null) {
            throw new \RuntimeException("Lead {$lead->number} already has quotation " . ($lead->quote->number ?? '') . '.');
        }

        if ($lead->isClosed()) {
            throw new \RuntimeException("Lead {$lead->number} is {$lead->statusLabel()} and cannot be quoted.");
        }

        return DB::transaction(function () use ($lead, $actor) {
            $quote = Quote::create([
                'tenant_id'        => $lead->tenant_id,
                'type'             => Quote::TYPE_QUOTATION,
                'number'           => Quote::nextNumber($lead->tenant_id, Quote::TYPE_QUOTATION),
                'status'           => 'draft',
                'subscriber_id'    => $lead->subscriber_id,
                'customer_name'    => $lead->company ?: $lead->name,
                'customer_email'   => $lead->email,
                'customer_phone'   => $lead->phone,
                'customer_address' => $lead->address,
                'issue_date'       => now()->toDateString(),
                'valid_until'      => now()->addDays(15)->toDateString(),
                'notes'            => "Raised from lead {$lead->number}.",
            ]);

            // Seed one line from the lead so the document is not empty. Priced
            // from the plan when one is attached, else the estimated value.
            $unitPrice = $lead->plan?->price ?? $lead->estimated_value;
            if ($unitPrice > 0) {
                QuoteItem::create(array_merge([
                    'tenant_id' => $lead->tenant_id,
                    'quote_id'  => $quote->id,
                    'label'     => $lead->plan->name ?? 'Proposed service',
                    'sort_order' => 0,
                ], QuoteItem::computeLine((float) $unitPrice, 1, false, 0.0)));
            }

            $quote->recomputeTotals();

            $lead->quote_id = $quote->id;
            // A sent proposal is exactly what the `proposal` stage means.
            if (in_array($lead->status, ['new', 'contacted', 'qualified'], true)) {
                $this->applyStatus($lead, 'proposal', $actor);
            }
            $lead->save();

            LeadActivity::create([
                'tenant_id'   => $lead->tenant_id,
                'lead_id'     => $lead->id,
                'type'        => 'quoted',
                'note'        => "Quotation {$quote->number} raised.",
                'occurred_at' => now(),
                'actor'       => $actor,
            ]);

            return $quote->fresh(['items']);
        });
    }

    /**
     * Record the stage change on the model and append the trail row.
     *
     * Does NOT save — callers set their own extra columns first and save once.
     */
    private function applyStatus(Lead $lead, string $status, ?string $actor, ?string $note = null): void
    {
        $from = $lead->status;
        if ($from === $status) {
            return;
        }

        $lead->status = $status;

        LeadActivity::create([
            'tenant_id'   => $lead->tenant_id,
            'lead_id'     => $lead->id,
            'type'        => 'status',
            'from_status' => $from,
            'to_status'   => $status,
            'note'        => $note,
            'occurred_at' => now(),
            'actor'       => $actor,
        ]);
    }
}