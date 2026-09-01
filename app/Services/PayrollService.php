<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Payslip;
use App\Models\Staff;
use Illuminate\Support\Carbon;

/**
 * Turns attendance into payslips.
 *
 * Proration model: the payroll month has N *working days* (roster days that
 * are not a week off / holiday). Earned basic = basic x (payable / working).
 * Days with no attendance row at all are treated as PRESENT rather than absent
 * — an ISP back office marks exceptions, not every normal day, and defaulting
 * to absent would silently zero out salaries.
 */
final class PayrollService
{
    /** Statutory PF ceiling: PF applies to the first 15,000 of earned basic. */
    private const PF_WAGE_CEILING = 15000.0;

    /**
     * Build (or refresh) the payslip for one employee and period.
     *
     * @param  string $periodMonth Any date inside the payroll month.
     * @param  array<string,float> $extras bonus / tds / advance_deduction / other_deductions
     */
    public function generate(Staff $staff, string $periodMonth, array $extras = []): Payslip
    {
        $start = Carbon::parse($periodMonth)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $summary = $this->attendanceSummary($staff, $start, $end);

        $basic  = (float) $staff->basic_salary;
        $ratio  = $summary['working_days'] > 0
            ? $summary['payable_days'] / $summary['working_days']
            : 0.0;

        $earnedBasic = round($basic * $ratio, 2);
        $hra         = round((float) $staff->hra * $ratio, 2);
        $allowances  = round((float) $staff->other_allowances * $ratio, 2);
        $overtime    = round($summary['overtime_hours'] * (float) $staff->overtime_rate_per_hour, 2);

        $bonus = round((float) ($extras['bonus'] ?? 0), 2);
        $gross = round($earnedBasic + $hra + $allowances + $overtime + $bonus, 2);

        // PF is on earned basic capped at the statutory ceiling; ESI on gross.
        $pf  = round(min($earnedBasic, self::PF_WAGE_CEILING) * (float) $staff->pf_percent / 100, 2);
        $esi = round($gross * (float) $staff->esi_percent / 100, 2);
        $pt  = $summary['payable_days'] > 0 ? round((float) $staff->professional_tax, 2) : 0.0;

        $tds      = round((float) ($extras['tds'] ?? 0), 2);
        $advance  = round((float) ($extras['advance_deduction'] ?? 0), 2);
        $other    = round((float) ($extras['other_deductions'] ?? 0), 2);
        $deducted = round($pf + $esi + $pt + $tds + $advance + $other, 2);

        $existing = Payslip::where('tenant_id', $staff->tenant_id)
            ->where('staff_id', $staff->id)
            ->whereDate('period_month', $start->toDateString())
            ->first();

        // A paid payslip is a document already handed to the employee.
        if ($existing && $existing->isLocked()) {
            return $existing;
        }

        $attributes = [
            'tenant_id'         => $staff->tenant_id,
            'staff_id'          => $staff->id,
            'period_month'      => $start->toDateString(),
            'working_days'      => $summary['working_days'],
            'payable_days'      => $summary['payable_days'],
            'lop_days'          => round($summary['working_days'] - $summary['payable_days'], 2),
            'overtime_hours'    => $summary['overtime_hours'],
            'basic_salary'      => $basic,
            'earned_basic'      => $earnedBasic,
            'hra'               => $hra,
            'other_allowances'  => $allowances,
            'overtime_amount'   => $overtime,
            'bonus'             => $bonus,
            'gross_earnings'    => $gross,
            'pf_amount'         => $pf,
            'esi_amount'        => $esi,
            'professional_tax'  => $pt,
            'tds'               => $tds,
            'advance_deduction' => $advance,
            'other_deductions'  => $other,
            'total_deductions'  => $deducted,
            'net_pay'           => round($gross - $deducted, 2),
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        // `number` is only assigned once so a regenerated draft keeps its id.
        $attributes['number'] = Payslip::nextNumber($staff->tenant_id, $start->toDateString());
        $attributes['status'] = 'draft';

        return Payslip::create($attributes);
    }

    /**
     * Generate for every assignable employee of a tenant.
     *
     * @return array{created:int, updated:int, locked:int}
     */
    public function generateForTenant(int|string $tenantId, string $periodMonth): array
    {
        $result = ['created' => 0, 'updated' => 0, 'locked' => 0];

        $staff = Staff::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'on_leave'])
            ->orderBy('code')
            ->get();

        foreach ($staff as $member) {
            $payslip = $this->generate($member, $periodMonth);

            if ($payslip->wasRecentlyCreated) {
                $result['created']++;
            } elseif ($payslip->isLocked()) {
                $result['locked']++;
            } else {
                $result['updated']++;
            }
        }

        return $result;
    }

    /**
     * Roster + payable-day totals for a period.
     *
     * @return array{working_days:int, payable_days:float, overtime_hours:float, counts:array<string,int>}
     */
    public function attendanceSummary(Staff $staff, Carbon $start, Carbon $end): array
    {
        $rows = Attendance::where('staff_id', $staff->id)
            // Carbon bounds, not "Y-m-d" strings: the `date` cast stores
            // "Y-m-d 00:00:00", which sorts AFTER the bare end-date string and
            // would silently drop the last day of the month on SQLite.
            ->whereBetween('work_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());

        $workingDays = 0;
        $payableDays = 0.0;
        $overtime    = 0.0;
        $counts      = array_fill_keys(array_keys(Attendance::STATUSES), 0);

        // Joining / leaving dates clip the period so a mid-month hire is not
        // penalised for days before they existed.
        $from = $staff->date_of_joining && $staff->date_of_joining->gt($start)
            ? $staff->date_of_joining->copy()
            : $start->copy();
        $to = $staff->date_of_leaving && $staff->date_of_leaving->lt($end)
            ? $staff->date_of_leaving->copy()
            : $end->copy();

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $row = $rows->get($day->toDateString());

            if ($row) {
                $status   = $row->status;
                $overtime += (float) $row->overtime_hours;
            } else {
                // No row = a normal day, except Sunday which is the default off.
                $status = $day->isSunday() ? 'week_off' : 'present';
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if (in_array($status, Attendance::WORKING_STATUSES, true)) {
                $workingDays++;
                $payableDays += $row
                    ? (float) $row->payable_days
                    : Attendance::weightFor($status);
            }
        }

        return [
            'working_days'   => $workingDays,
            'payable_days'   => round($payableDays, 2),
            'overtime_hours' => round($overtime, 2),
            'counts'         => $counts,
        ];
    }
}
