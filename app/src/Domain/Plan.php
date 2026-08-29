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
        public ?int $taxRateId = null,       // link to a managed TaxRate
    ) {}

    /**
     * Tax amount for a given pre-tax subtotal (rounded to 2 decimals).
     * Uses the linked TaxRate when present, else the plan's own taxRate.
     */
    public function taxFor(float $subtotal): float
    {
        if ($this->taxRateId !== null && $this->linkedTaxRate !== null) {
            return $this->linkedTaxRate->taxFor($subtotal);
        }
        return round($subtotal * ($this->taxRate / 100), 2);
    }

    /** Resolved managed TaxRate (set by the repository when joined). */
    public ?TaxRate $linkedTaxRate = null;
}
