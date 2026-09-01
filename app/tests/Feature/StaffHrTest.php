<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Payslip;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff & HR: employee CRUD, the attendance register and payroll derivation.
 */
class StaffHrTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'HR ISP', 'domain' => 'hr.test', 'slug' => 'hr', 'status' => 'active',
        ]);
    }

    private function url(string $path): string
    {
        // ResolveTenant keys off the request Host, so a FULL url is required.
        return 'http://' . $this->tenant->domain . '/' . ltrim($path, '/');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'Ravi Kumar',
            'role'            => 'technician',
            'employment_type' => 'full_time',
            'status'          => 'active',
            'basic_salary'    => 30000,
            'hra'             => 12000,
            'other_allowances' => 3000,
            'pf_percent'      => 12,
            'esi_percent'     => 0.75,
            'professional_tax' => 200,
            'overtime_rate_per_hour' => 150,
        ], $overrides);
    }

    private function makeStaff(array $overrides = []): Staff
    {
        return Staff::create($this->payload(array_merge([
            'tenant_id' => $this->tenant->id,
            'code'      => Staff::nextCode($this->tenant->id),
        ], $overrides)));
    }

    public function test_staff_is_created_with_an_auto_generated_code(): void
    {
        $this->post($this->url('/staff'), $this->payload(['code' => '']))
            ->assertRedirect($this->url('/staff'));

        $member = Staff::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('ST-0001', $member->code);
        $this->assertSame(45000.0, $member->grossSalary());

        $this->post($this->url('/staff'), $this->payload(['name' => 'Second', 'code' => '']))
            ->assertRedirect();
        $this->assertSame('ST-0002', Staff::where('name', 'Second')->firstOrFail()->code);
    }

    public function test_an_employee_cannot_report_to_themselves(): void
    {
        $member = $this->makeStaff();

        $this->put($this->url('/staff/' . $member->id), $this->payload([
            'reports_to_id' => $member->id,
        ]))->assertSessionHasErrors('reports_to_id');
    }

    public function test_attendance_register_saves_one_row_per_staff_per_day_and_is_idempotent(): void
    {
        $member = $this->makeStaff();

        $post = fn (string $status) => $this->post($this->url('/attendance'), [
            'date' => '2026-09-02',
            'rows' => [[
                'staff_id'  => $member->id,
                'status'    => $status,
                'check_in'  => '09:30',
                'check_out' => '18:00',
            ]],
        ]);

        $post('present')->assertRedirect();
        $post('half_day')->assertRedirect();

        // unique(staff_id, work_date) + updateOrCreate = correction, not a duplicate.
        $rows = Attendance::where('staff_id', $member->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('half_day', $rows->first()->status);
        $this->assertSame(0.5, $rows->first()->payable_days);
        $this->assertSame(8.5, $rows->first()->hours_worked);
    }

    public function test_unpaid_leave_reduces_the_payable_days_and_the_net_pay(): void
    {
        $member = $this->makeStaff(['date_of_joining' => '2026-01-01']);

        $full = app(PayrollService::class)->generate($member, '2026-09-01');
        // No attendance rows at all → every non-Sunday counts as present.
        $this->assertSame($full->working_days, (int) $full->payable_days);
        $this->assertSame(30000.0, $full->earned_basic);

        // Two unpaid days in the same month must cut the earned basic.
        foreach (['2026-09-02', '2026-09-03'] as $date) {
            Attendance::create([
                'tenant_id'    => $this->tenant->id,
                'staff_id'     => $member->id,
                'work_date'    => $date,
                'status'       => 'unpaid_leave',
                'payable_days' => Attendance::weightFor('unpaid_leave'),
            ]);
        }

        $reduced = app(PayrollService::class)->generate($member, '2026-09-01');
        $this->assertSame(2.0, $reduced->lop_days);
        $this->assertLessThan($full->earned_basic, $reduced->earned_basic);
        $this->assertLessThan($full->net_pay, $reduced->net_pay);

        // Regenerating reuses the same payslip rather than making a second one.
        $this->assertSame(1, Payslip::where('staff_id', $member->id)->count());
        $this->assertSame($full->number, $reduced->number);
    }

    public function test_pf_is_capped_at_the_statutory_wage_ceiling(): void
    {
        // Basic 30,000 with PF 12% → 12% of the 15,000 ceiling, not of 30,000.
        $member  = $this->makeStaff();
        $payslip = app(PayrollService::class)->generate($member, '2026-09-01');

        $this->assertSame(1800.0, $payslip->pf_amount);
    }

    public function test_a_paid_payslip_is_frozen(): void
    {
        $member  = $this->makeStaff();
        $payslip = app(PayrollService::class)->generate($member, '2026-09-01');

        $payslip->update(['status' => 'paid', 'paid_at' => now()]);
        $frozenNet = $payslip->net_pay;

        // A new unpaid-leave row would normally reduce the net pay.
        Attendance::create([
            'tenant_id'    => $this->tenant->id,
            'staff_id'     => $member->id,
            'work_date'    => '2026-09-04',
            'status'       => 'unpaid_leave',
            'payable_days' => 0,
        ]);

        $again = app(PayrollService::class)->generate($member, '2026-09-01');
        $this->assertSame($frozenNet, $again->net_pay);

        // The edit endpoint refuses it too.
        $this->put($this->url('/payroll/' . $payslip->id), ['status' => 'draft'])
            ->assertSessionHasErrors('payslip');
    }

    public function test_payroll_run_generates_a_payslip_for_every_active_employee(): void
    {
        $this->makeStaff(['name' => 'A']);
        $this->makeStaff(['name' => 'B', 'code' => 'ST-0002']);
        $this->makeStaff(['name' => 'Gone', 'code' => 'ST-0003', 'status' => 'resigned']);

        $this->post($this->url('/payroll'), ['period_month' => '2026-09-01'])->assertRedirect();

        // Resigned staff are excluded from the run.
        $this->assertSame(2, Payslip::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_staff_with_payroll_history_cannot_be_deleted(): void
    {
        $member = $this->makeStaff();
        app(PayrollService::class)->generate($member, '2026-09-01');

        $this->delete($this->url('/staff/' . $member->id))
            ->assertSessionHasErrors('staff');

        $this->assertNotNull(Staff::find($member->id));
    }

    public function test_staff_are_scoped_to_their_tenant(): void
    {
        $this->post($this->url('/staff'), $this->payload(['code' => 'ST-A']))->assertRedirect();

        $other = Tenant::create([
            'name' => 'Other HR', 'domain' => 'other-hr.test', 'slug' => 'otherhr', 'status' => 'active',
        ]);

        // Same code in another tenant is allowed; the listing must not leak.
        $this->post('http://' . $other->domain . '/staff', $this->payload(['code' => 'ST-A']))
            ->assertRedirect();

        $this->assertSame(2, Staff::where('code', 'ST-A')->count());
        $this->get('http://' . $other->domain . '/staff')->assertOk();
    }
}
