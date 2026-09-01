<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monthly payslip per employee (`unique(staff_id, period_month)`).
 *
 * Computed by `App\Services\PayrollService` from the attendance rows of the
 * period. Once `status` is `paid` the document is frozen — see `isLocked()`.
 */
class Payslip extends Model
{
    public const STATUSES = [
        'draft'     => 'Draft',
        'approved'  => 'Approved',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = [
        'bank_transfer' => 'Bank Transfer',
        'cash'          => 'Cash',
        'cheque'        => 'Cheque',
        'upi'           => 'UPI',
    ];

    protected $fillable = [
        'tenant_id', 'staff_id', 'number', 'period_month',
        'working_days', 'payable_days', 'lop_days', 'overtime_hours',
        'basic_salary', 'earned_basic', 'hra', 'other_allowances',
        'overtime_amount', 'bonus', 'gross_earnings',
        'pf_amount', 'esi_amount', 'professional_tax', 'tds',
        'advance_deduction', 'other_deductions', 'total_deductions',
        'net_pay', 'status', 'payment_method', 'payment_reference', 'paid_at', 'notes',
    ];

    protected $casts = [
        'period_month'      => 'date',
        'paid_at'           => 'datetime',
        'working_days'      => 'integer',
        'payable_days'      => 'float',
        'lop_days'          => 'float',
        'overtime_hours'    => 'float',
        'basic_salary'      => 'float',
        'earned_basic'      => 'float',
        'hra'               => 'float',
        'other_allowances'  => 'float',
        'overtime_amount'   => 'float',
        'bonus'             => 'float',
        'gross_earnings'    => 'float',
        'pf_amount'         => 'float',
        'esi_amount'        => 'float',
        'professional_tax'  => 'float',
        'tds'               => 'float',
        'advance_deduction' => 'float',
        'other_deductions'  => 'float',
        'total_deductions'  => 'float',
        'net_pay'           => 'float',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function staff(): BelongsTo { return $this->belongsTo(Staff::class, 'staff_id'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function methodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method]
            ?? ($this->payment_method ? ucfirst(str_replace('_', ' ', $this->payment_method)) : '—');
    }

    public function periodLabel(): string
    {
        return $this->period_month?->format('F Y') ?? '—';
    }

    /** A paid payslip must not be recalculated or edited. */
    public function isLocked(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Next payslip number for a period: PS-2026-09-0001. Scoped per tenant so
     * two ISPs never collide (the column is unique per tenant).
     */
    public static function nextNumber(int|string $tenantId, string $periodMonth): string
    {
        $prefix = 'PS-' . date('Y-m', strtotime($periodMonth)) . '-';

        $last = self::where('tenant_id', $tenantId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->number, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . sprintf('%04d', $seq);
    }
}
