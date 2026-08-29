<?php

namespace App\Src\Domain;

use App\Src\Domain\TaxRate;

/**
 * Pure domain entity for a billing PLAN.
 *
 * Holds ONLY financial / billing details (name, price, cycle). The network
 * behaviour is delegated to a `BandwidthProfile` (synced to RADIUS) referenced
 * by `bandwidthProfileId`. This keeps billing local-only while bandwidth is the
 * RADIUS system of record. (Clean/Hexagonal: no DB / framework imports — §7.2.)
 */
final class Plan
{
    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $name,
        public float $price,
        public string $cycle,                // monthly|quarterly|yearly
        public ?int $bandwidthProfileId = null,
        /** @var TaxRate[] */
        public array $taxRates = [],         // managed taxes attached to this plan (0..n)
    ) {}

    /**
     * Total tax amount for a given pre-tax subtotal (rounded to 2 decimals).
     * Sums every attached managed tax rate. A plan with no taxes returns 0.
     */
    public function taxFor(float $subtotal): float
    {
        $total = 0.0;
        foreach ($this->taxRates as $tr) {
            $total += $tr->taxFor($subtotal);
        }
        return round($total, 2);
    }
}
