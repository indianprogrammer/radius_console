<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\Staff;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Payroll — Staff & HR.
 *
 * Payslips are always COMPUTED by `PayrollService` from attendance; the form
 * only supplies the discretionary figures (bonus, TDS, advance, other) and the
 * payment/status maintenance. A `paid` payslip is frozen (`isLocked()`).
 */
final class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request)
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $month   = $this->resolveMonth($request->query('month'));

        $payslips = Payslip::query()
            ->where('tenant_id', tenant_id())
            ->with('staff')
            ->whereDate('period_month', $month->toDateString())
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('number', 'like', "%{$search}%")
                      ->orWhereHas('staff', fn ($s) => $s->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->orderBy('number')
            ->paginate($perPage)
            ->withQueryString();

        return view('payroll.index', [
            'payslips' => $payslips,
            'month'    => $month,
            'search'   => $request->query('q'),
            'status'   => $request->query('status'),
            'totals'   => $this->summary($month),
        ]);
    }

    /** Form to run payroll: pick a month and (optionally) a single employee. */
    public function create(Request $request)
    {
        return view('payroll.create', [
            'month' => $this->resolveMonth($request->query('month')),
            'staff' => Staff::where('tenant_id', tenant_id())
                ->whereIn('status', ['active', 'on_leave'])
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'basic_salary']),
        ]);
    }

    /** Run payroll for the whole tenant or one employee. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'period_month' => 'required|date',
            'staff_id'     => [
                'nullable', 'integer',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', tenant_id())),
            ],
        ]);

        $month = Carbon::parse($data['period_month'])->startOfMonth();

        if (!empty($data['staff_id'])) {
            $member  = Staff::where('tenant_id', tenant_id())->findOrFail((int) $data['staff_id']);
            $payslip = $this->payroll->generate($member, $month->toDateString());

            $message = $payslip->isLocked()
                ? "Payslip {$payslip->number} is already paid and was not recalculated."
                : "Payslip {$payslip->number} generated for {$member->name}.";

            return redirect()->route('payroll.show', $payslip->id)->with('status', $message);
        }

        $result = $this->payroll->generateForTenant(tenant_id(), $month->toDateString());

        return redirect()->route('payroll.index', ['month' => $month->toDateString()])
            ->with('status', sprintf(
                'Payroll for %s: %d created, %d updated, %d already paid (skipped).',
                $month->format('F Y'),
                $result['created'],
                $result['updated'],
                $result['locked'],
            ));
    }

    public function show(int $id)
    {
        $payslip = Payslip::where('tenant_id', tenant_id())->with('staff')->findOrFail($id);

        return view('payroll.show', ['payslip' => $payslip]);
    }

    public function edit(int $id)
    {
        $payslip = Payslip::where('tenant_id', tenant_id())->with('staff')->findOrFail($id);

        return view('payroll.edit', ['payslip' => $payslip]);
    }

    /** Discretionary amounts + status/payment maintenance. Recomputes totals. */
    public function update(Request $request, int $id)
    {
        $payslip = Payslip::where('tenant_id', tenant_id())->findOrFail($id);

        if ($payslip->isLocked()) {
            return redirect()->route('payroll.show', $payslip->id)
                ->withErrors(['payslip' => "Payslip {$payslip->number} is paid and can no longer be edited."]);
        }

        $data = $request->validate([
            'bonus'             => 'nullable|numeric|min:0',
            'tds'               => 'nullable|numeric|min:0',
            'advance_deduction' => 'nullable|numeric|min:0',
            'other_deductions'  => 'nullable|numeric|min:0',
            'status'            => 'required|in:' . implode(',', array_keys(Payslip::STATUSES)),
            'payment_method'    => 'nullable|in:' . implode(',', array_keys(Payslip::PAYMENT_METHODS)),
            'payment_reference' => 'nullable|string|max:100',
            'paid_at'           => 'nullable|date',
            'notes'             => 'nullable|string|max:1000',
        ]);

        // Recompute from attendance so the new extras flow into gross/net.
        $payslip = $this->payroll->generate($payslip->staff, $payslip->period_month->toDateString(), [
            'bonus'             => (float) ($data['bonus'] ?? 0),
            'tds'               => (float) ($data['tds'] ?? 0),
            'advance_deduction' => (float) ($data['advance_deduction'] ?? 0),
            'other_deductions'  => (float) ($data['other_deductions'] ?? 0),
        ]);

        $payslip->update([
            'status'            => $data['status'],
            'payment_method'    => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
            // Marking it paid without a date stamps now, so the ledger is dated.
            'paid_at'           => $data['status'] === 'paid'
                ? ($data['paid_at'] ?? now())
                : ($data['paid_at'] ?? null),
            'notes'             => $data['notes'] ?? null,
        ]);

        return redirect()->route('payroll.show', $payslip->id)
            ->with('status', "Payslip {$payslip->number} updated.");
    }

    public function destroy(Request $request, int $id)
    {
        $payslip = Payslip::where('tenant_id', tenant_id())->findOrFail($id);
        $number = $payslip->number;

        if ($payslip->isLocked()) {
            $message = "Payslip {$number} is paid — cancel it instead of deleting.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return redirect()->route('payroll.index')->withErrors(['payslip' => $message]);
        }

        $payslip->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => "Payslip {$number} deleted."]);
        }

        return redirect()->route('payroll.index')->with('status', "Payslip {$number} deleted.");
    }

    private function resolveMonth(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            return Carbon::now()->startOfMonth();
        }
    }

    private function summary(Carbon $month): array
    {
        $base = fn () => Payslip::where('tenant_id', tenant_id())
            ->whereDate('period_month', $month->toDateString());

        return [
            'count'      => $base()->count(),
            'gross'      => round((float) $base()->sum('gross_earnings'), 2),
            'deductions' => round((float) $base()->sum('total_deductions'), 2),
            'net'        => round((float) $base()->sum('net_pay'), 2),
            'paid'       => round((float) $base()->where('status', 'paid')->sum('net_pay'), 2),
        ];
    }
}
