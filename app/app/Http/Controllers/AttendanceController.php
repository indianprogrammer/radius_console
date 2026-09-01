<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Attendance register — Staff & HR.
 *
 * The primary screen is a per-DAY register: every assignable employee is listed
 * with their mark for the chosen date, and the whole day is saved in one post
 * (`bulkStore`). `unique(staff_id, work_date)` plus `updateOrCreate` makes the
 * save idempotent, so re-submitting a day corrects it instead of duplicating.
 */
final class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->resolveDate($request->query('date'));

        $staff = Staff::where('tenant_id', tenant_id())
            ->whereIn('status', ['active', 'on_leave'])
            // Not yet joined / already left employees have no row for this day.
            ->where(function ($q) use ($date) {
                $q->whereNull('date_of_joining')->orWhereDate('date_of_joining', '<=', $date->toDateString());
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('date_of_leaving')->orWhereDate('date_of_leaving', '>=', $date->toDateString());
            })
            ->orderBy('name')
            ->get();

        $rows = Attendance::where('tenant_id', tenant_id())
            ->whereDate('work_date', $date->toDateString())
            ->get()
            ->keyBy('staff_id');

        return view('attendance.index', [
            'date'    => $date,
            'staff'   => $staff,
            'rows'    => $rows,
            'totals'  => $this->dayTotals($rows, $staff->count(), $date),
        ]);
    }

    /** Save the whole register for one date. */
    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'date'                  => 'required|date',
            'rows'                  => 'required|array',
            'rows.*.staff_id'       => [
                'required', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
            'rows.*.status'         => 'required|in:' . implode(',', array_keys(Attendance::STATUSES)),
            'rows.*.check_in'       => 'nullable|date_format:H:i',
            'rows.*.check_out'      => 'nullable|date_format:H:i',
            'rows.*.overtime_hours' => 'nullable|numeric|min:0|max:24',
            'rows.*.remarks'        => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($data['date'])->startOfDay();
        $saved = 0;

        foreach ($data['rows'] as $row) {
            $hours = Attendance::hoursBetween($row['check_in'] ?? null, $row['check_out'] ?? null);

            $values = [
                'tenant_id'      => tenant_id(),
                'staff_id'       => (int) $row['staff_id'],
                'work_date'      => $date->toDateString(),
                'status'         => $row['status'],
                'check_in'       => $row['check_in'] ?? null,
                'check_out'      => $row['check_out'] ?? null,
                'hours_worked'   => $hours ?? 0,
                'overtime_hours' => (float) ($row['overtime_hours'] ?? 0),
                // Stored, not derived, so old payslips stay reproducible.
                'payable_days'   => Attendance::weightFor($row['status']),
                'remarks'        => $row['remarks'] ?? null,
            ];

            // NOT updateOrCreate: `work_date` is a `date` cast, so the stored
            // value is "Y-m-d 00:00:00" and a bare "Y-m-d" in the WHERE never
            // matches on SQLite — the unique index would then reject the save.
            $existing = Attendance::where('staff_id', $values['staff_id'])
                ->whereDate('work_date', $date->toDateString())
                ->first();

            $existing ? $existing->update($values) : Attendance::create($values);
            $saved++;
        }

        return redirect()->route('attendance.index', ['date' => $date->toDateString()])
            ->with('status', "Attendance saved for {$saved} staff on " . $date->format('d/m/Y') . '.');
    }

    /** Per-employee monthly sheet, with the payroll summary for that month. */
    public function sheet(Request $request, int $staff)
    {
        $member = Staff::where('tenant_id', tenant_id())->findOrFail($staff);

        $month = $this->resolveMonth($request->query('month'));
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        $rows = $member->attendance()
            // Carbon bounds, not "Y-m-d" strings: the `date` cast stores
            // "Y-m-d 00:00:00", which sorts AFTER the bare end-date string.
            ->whereBetween('work_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());

        return view('attendance.sheet', [
            'member'  => $member,
            'month'   => $month,
            'start'   => $start,
            'end'     => $end,
            'rows'    => $rows,
            'summary' => app(\App\Services\PayrollService::class)->attendanceSummary($member, $start, $end),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $row = Attendance::where('tenant_id', tenant_id())->findOrFail($id);
        $date = $row->work_date->toDateString();
        $row->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Attendance entry removed.']);
        }

        return redirect()->route('attendance.index', ['date' => $date])
            ->with('status', 'Attendance entry removed.');
    }

    private function resolveDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }

    private function resolveMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }

    /** Header tiles for the register. Unmarked staff default to present. */
    private function dayTotals($rows, int $headcount, Carbon $date): array
    {
        $marked = $rows->count();

        return [
            'headcount' => $headcount,
            'marked'    => $marked,
            'present'   => $rows->whereIn('status', ['present', 'half_day'])->count(),
            'absent'    => $rows->whereIn('status', ['absent', 'unpaid_leave'])->count(),
            'leave'     => $rows->where('status', 'paid_leave')->count(),
            'unmarked'  => max(0, $headcount - $marked),
            'label'     => $date->format('d/m/Y'),
        ];
    }
}
