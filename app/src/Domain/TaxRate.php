<?php

namespace App\Src\Domain;

/**
 * Pure domain entity for a managed TAX RATE.
 *
 * Defined under Billing & Invoices so a tenant can create reusable taxes
 * (e.g. "VAT 18%") and attach them to billing plans. No DB / framework imports
 * (Clean/Hexagonal: §7.2).
 */
final class TaxRate
{
    public function __construct(
        public ?int $id,
        public string $tenantId,
        public string $name,
        public float $rate,                 // percentage, e.g. 18.0
        public string $type = 'percentage', // percentage|fixed
        public bool $isDefault = false,
    ) {}

    /**
     * Tax amount for a given pre-tax subtotal (percentage type).
     */
    public function taxFor(float $subtotal): float
    {
        if ($this->type === 'fixed') {
            return round($this->rate, 2);
        }
        return round($subtotal * ($this->rate / 100), 2);
    }
}
