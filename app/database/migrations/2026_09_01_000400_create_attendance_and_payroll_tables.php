<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance & Payroll — "Staff & HR" menu group.
 *
 * `attendance` holds ONE row per staff member per day (unique constraint), so
 * marking attendance twice updates rather than duplicates. `payable_days` is
 * the weight that day contributes to salary (present/wfh = 1, half_day = 0.5,
 * absent = 0, paid leave = 1, unpaid leave = 0) — stored rather than derived so
 * a historical payslip stays reproducible if the policy later changes.
 *
 * `payslips` is the monthly run per employee. `period_month` is the first day
 * of the month (a DATE, not a string, so ordering and range queries work).
 * A payslip is unique per staff member per period, and once `status` is `paid`
 * it is frozen — recalculation is refused rather than silently overwriting a
 * document the employee has already been given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $t->date('work_date');
            // present|absent|half_day|paid_leave|unpaid_leave|week_off|holiday
            $t->string('status', 20)->default('present');
            $t->time('check_in')->nullable();
            $t->time('check_out')->nullable();
            $t->decimal('hours_worked', 6, 2)->default(0);
            $t->decimal('overtime_hours', 6, 2)->default(0);
            // Weight this day carries in the payroll calculation (0, 0.5 or 1).
            $t->decimal('payable_days', 4, 2)->default(1);
            $t->string('remarks', 500)->nullable();

            $t->timestamps();

            $t->unique(['staff_id', 'work_date']);
            $t->index(['tenant_id', 'work_date']);
        });

        Schema::create('payslips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $t->string('number', 40);              // PS-2026-09-0001
            $t->date('period_month');              // first day of the payroll month

            // ---- Attendance snapshot the payslip was computed from ----
            $t->integer('working_days')->default(0);
            $t->decimal('payable_days', 6, 2)->default(0);
            $t->decimal('lop_days', 6, 2)->default(0);   // loss of pay
            $t->decimal('overtime_hours', 8, 2)->default(0);

            // ---- Earnings ----
            $t->decimal('basic_salary', 12, 2)->default(0);   // full monthly basic
            $t->decimal('earned_basic', 12, 2)->default(0);   // prorated by payable days
            $t->decimal('hra', 12, 2)->default(0);
            $t->decimal('other_allowances', 12, 2)->default(0);
            $t->decimal('overtime_amount', 12, 2)->default(0);
            $t->decimal('bonus', 12, 2)->default(0);
            $t->decimal('gross_earnings', 12, 2)->default(0);

            // ---- Deductions ----
            $t->decimal('pf_amount', 12, 2)->default(0);
            $t->decimal('esi_amount', 12, 2)->default(0);
            $t->decimal('professional_tax', 12, 2)->default(0);
            $t->decimal('tds', 12, 2)->default(0);
            $t->decimal('advance_deduction', 12, 2)->default(0);
            $t->decimal('other_deductions', 12, 2)->default(0);
            $t->decimal('total_deductions', 12, 2)->default(0);

            $t->decimal('net_pay', 12, 2)->default(0);

            $t->string('status', 20)->default('draft');  // draft|approved|paid|cancelled
            $t->string('payment_method', 20)->nullable();
            $t->string('payment_reference', 100)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->text('notes')->nullable();

            $t->timestamps();

            $t->unique(['staff_id', 'period_month']);
            $t->unique(['tenant_id', 'number']);
            $t->index(['tenant_id', 'period_month']);
        });

        if (config('database.default') === 'pgsql') {
            foreach (['attendance', 'payslips'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation_{$table} ON {$table} USING (tenant_id = current_setting('app.current_tenant')::bigint)");
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            foreach (['payslips', 'attendance'] as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_{$table} ON {$table}");
            }
        }

        Schema::dropIfExists('payslips');
        Schema::dropIfExists('attendance');
    }
};
