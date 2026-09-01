<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment received against an invoice (or on account when `invoice_id` is
 * null). Recording / voiding a payment recomputes the parent invoice's
 * `paid_amount` and `status` via Invoice::refreshStatus().
 */
class Payment extends Model
{
    public const METHODS = [
        'cash'        => 'Cash',
        'upi'         => 'UPI',
        'card'        => 'Card',
        'netbanking'  => 'Net Banking',
        'cheque'      => 'Cheque',
        'wallet'      => 'Wallet',
        'adjustment'  => 'Adjustment',
    ];

    public const STATUSES = [
        'completed' => 'Completed',
        'pending'   => 'Pending',
        'failed'    => 'Failed',
    ];

    protected $fillable = [
        'tenant_id', 'subscriber_id', 'invoice_id', 'number', 'amount',
        'method', 'reference', 'paid_at', 'status', 'notes',
    ];

    protected $casts = [
        'amount'  => 'float',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }

    /** Next receipt number for the tenant: RCP-{yymm}-{0001}. */
    public static function nextNumber(int|string $tenantId): string
    {
        $prefix = date('ym');
        $last = self::where('tenant_id', $tenantId)
            ->where('number', 'like', "RCP-{$prefix}-%")
            ->orderByDesc('number')
            ->first();

        $seq = 1;
        if ($last && preg_match('/RCP-\d+-(\d+)/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('RCP-%s-%04d', $prefix, $seq);
    }
}
