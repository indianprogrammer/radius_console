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
        public float $taxRate = 0.0,         // % applied when invoicing (e.g. 18.0)
        /** @var TaxRate[] */
        public array $taxRates = [],         // managed taxes attached to this plan (0..n)
    ) {}

    /**
     * Total tax amount for a given pre-tax subtotal (rounded to 2 decimals).
     * Sums every attached managed tax rate. Falls back to the plan's own
     * flat `taxRate` % only when no managed taxes are attached.
     */
    public function taxFor(float $subtotal): float
    {
        if (!empty($this->taxRates)) {
            $total = 0.0;
            foreach ($this->taxRates as $tr) {
                $total += $tr->taxFor($subtotal);
            }
            return round($total, 2);
        }
        return round($subtotal * ($this->taxRate / 100), 2);
    }
}
