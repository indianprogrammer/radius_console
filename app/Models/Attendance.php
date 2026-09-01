<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attendance row per staff member per day (`unique(staff_id, work_date)`).
 *
 * `payable_days` is the salary weight of the day and is stored, not derived, so
 * an already-generated payslip stays reproducible even if the policy in
 * `PAYABLE_WEIGHTS` is later changed.
 */
class Attendance extends Model
{
    protected $table = 'attendance';

    public const STATUSES = [
        'present'      => 'Present',
        'half_day'     => 'Half Day',
        'paid_leave'   => 'Paid Leave',
        'unpaid_leave' => 'Unpaid Leave',
        'absent'       => 'Absent',
        'week_off'     => 'Week Off',
        'holiday'      => 'Holiday',
    ];

    /**
     * Salary weight per status. Week off / holiday count as paid days because
     * the monthly salary already covers them.
     */
    public const PAYABLE_WEIGHTS = [
        'present'      => 1.0,
        'half_day'     => 0.5,
        'paid_leave'   => 1.0,
        'unpaid_leave' => 0.0,
        'absent'       => 0.0,
        'week_off'     => 1.0,
        'holiday'      => 1.0,
    ];

    /** Statuses that consume a working day of the roster. */
    public const WORKING_STATUSES = ['present', 'half_day', 'paid_leave', 'unpaid_leave', 'absent'];

    protected $fillable = [
        'tenant_id', 'staff_id', 'work_date', 'status',
        'check_in', 'check_out', 'hours_worked', 'overtime_hours',
        'payable_days', 'remarks',
    ];

    protected $casts = [
        'work_date'      => 'date',
        'hours_worked'   => 'float',
        'overtime_hours' => 'float',
        'payable_days'   => 'float',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function staff(): BelongsTo { return $this->belongsTo(Staff::class, 'staff_id'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public static function weightFor(string $status): float
    {
        return self::PAYABLE_WEIGHTS[$status] ?? 0.0;
    }

    /**
     * Hours between check-in and check-out, rounded to 2dp. Returns null when
     * either end is missing so the caller can keep a manually entered figure.
     */
    public static function hoursBetween(?string $in, ?string $out): ?float
    {
        if (!$in || !$out) {
            return null;
        }

        $start = strtotime('1970-01-01 ' . $in);
        $end   = strtotime('1970-01-01 ' . $out);

        if ($start === false || $end === false) {
            return null;
        }

        // A shift crossing midnight ends "before" it starts on a single date.
        if ($end < $start) {
            $end += 86400;
        }

        return round(($end - $start) / 3600, 2);
    }
}
