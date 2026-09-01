<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Franchise / LCO — Franchise Management (SRD §5.4).
 *
 * Tenant-scoped reseller. `parent_id` allows a franchise hierarchy
 * (distributor -> franchise -> LCO). `balance` is the prepaid wallet figure
 * and is system-maintained; `credit_limit` is the allowed overdraft, so the
 * spendable figure is `availableCredit()`.
 */
class Franchise extends Model
{
    public const TYPES = [
        'distributor' => 'Distributor',
        'franchise'   => 'Franchise',
        'lco'         => 'LCO',
    ];

    public const STATUSES = [
        'active'    => 'Active',
        'suspended' => 'Suspended',
        'inactive'  => 'Inactive',
    ];

    public const COMMISSION_TYPES = [
        'percentage' => 'Percentage (%)',
        'fixed'      => 'Fixed Amount',
    ];

    protected $fillable = [
        'tenant_id', 'parent_id', 'code', 'name', 'type',
        'contact_person', 'email', 'phone',
        'address', 'city', 'state', 'pincode',
        'gst_number', 'pan_number',
        'commission_type', 'commission_rate', 'credit_limit', 'balance',
        'status', 'notes',
    ];

    protected $casts = [
        'commission_rate' => 'float',
        'credit_limit'    => 'float',
        'balance'         => 'float',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }

    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Wallet balance plus the allowed overdraft (SRD §5.4 credit limit). */
    public function availableCredit(): float
    {
        return round((float) $this->balance + (float) $this->credit_limit, 2);
    }

    /** Human-readable commission, e.g. "12.50%" or "150.00". */
    public function commissionLabel(): string
    {
        return number_format((float) $this->commission_rate, 2)
            . ($this->commission_type === 'fixed' ? '' : '%');
    }

    /**
     * Next franchise code for the tenant: FR-0001. Used when the operator
     * leaves the code blank on the create form.
     */
    public static function nextCode(int|string $tenantId): string
    {
        $last = self::where('tenant_id', $tenantId)
            ->where('code', 'like', 'FR-%')
            ->orderByDesc('code')
            ->first();

        $seq = 1;
        if ($last && preg_match('/FR-(\d+)/', $last->code, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('FR-%04d', $seq);
    }
}
