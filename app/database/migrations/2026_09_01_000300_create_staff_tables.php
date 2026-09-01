<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff / HR — "Staff & HR" menu group (SRD §5.0 #2, §5.4 roles).
 *
 * `staff` is the employee master. It is deliberately SEPARATE from `users`
 * (the RBAC login table, §8): not every employee gets a console login, and a
 * login may outlive an employment record. `user_id` is the optional bridge.
 *
 * Salary components are stored per employee so a payslip can be recomputed
 * from attendance without re-deriving policy: gross = basic + hra +
 * other_allowances, and the statutory deductions use the percentages here.
 *
 * `staff_groups` + `staff_group_members` give a many-to-many team grouping
 * (e.g. "Field Technicians — North") so a ticket can be assigned to a whole
 * team in one action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            // Optional console login (RBAC user) for this employee.
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Employees may belong to a franchise / LCO instead of head office.
            $t->foreignId('franchise_id')->nullable()->constrained('franchises')->nullOnDelete();
            // Reporting manager (self-referencing).
            $t->foreignId('reports_to_id')->nullable()->constrained('staff')->nullOnDelete();

            $t->string('code', 40);                        // unique per tenant, ST-0001
            $t->string('name', 150);
            $t->string('designation', 100)->nullable();
            $t->string('department', 100)->nullable();
            $t->string('role', 30)->default('technician');  // mirrors users.role ladder

            $t->string('email', 150)->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('emergency_contact', 20)->nullable();

            $t->date('date_of_birth')->nullable();
            $t->date('date_of_joining')->nullable();
            $t->date('date_of_leaving')->nullable();
            $t->string('employment_type', 20)->default('full_time'); // full_time|part_time|contract|intern

            $t->text('address')->nullable();
            $t->string('city', 100)->nullable();
            $t->string('state', 100)->nullable();
            $t->string('pincode', 12)->nullable();

            // Compliance / bank (payout details).
            $t->string('pan_number', 20)->nullable();
            $t->string('aadhaar_number', 20)->nullable();
            $t->string('bank_account_name', 150)->nullable();
            $t->string('bank_account_number', 40)->nullable();
            $t->string('bank_ifsc', 20)->nullable();

            // ---- Salary structure (monthly figures) ----
            $t->decimal('basic_salary', 12, 2)->default(0);
            $t->decimal('hra', 12, 2)->default(0);
            $t->decimal('other_allowances', 12, 2)->default(0);
            $t->decimal('pf_percent', 5, 2)->default(0);          // % of earned basic
            $t->decimal('esi_percent', 5, 2)->default(0);         // % of earned gross
            $t->decimal('professional_tax', 10, 2)->default(0);   // flat per month
            $t->decimal('overtime_rate_per_hour', 10, 2)->default(0);

            $t->string('status', 20)->default('active');   // active|on_leave|suspended|resigned|terminated
            $t->text('notes')->nullable();

            $t->timestamps();

            $t->unique(['tenant_id', 'code']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('staff_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('name', 150);
            $t->string('description', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['tenant_id', 'name']);
        });

        Schema::create('staff_group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('staff_group_id')->constrained('staff_groups')->cascadeOnDelete();
            $t->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['staff_group_id', 'staff_id']);
        });

        if (config('database.default') === 'pgsql') {
            foreach (['staff', 'staff_groups', 'staff_group_members'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation_{$table} ON {$table} USING (tenant_id = current_setting('app.current_tenant')::bigint)");
            }
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            foreach (['staff_group_members', 'staff_groups', 'staff'] as $table) {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_{$table} ON {$table}");
            }
        }

        Schema::dropIfExists('staff_group_members');
        Schema::dropIfExists('staff_groups');
        Schema::dropIfExists('staff');
    }
};
