<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Staff / employee master — Staff & HR.
 *
 * Separate from `App\Models\User` (the RBAC login): an employee need not have
 * console access, and the salary structure has no place on an auth model.
 * `user_id` links the two when a login does exist.
 */
class Staff extends Model
{
    /** Laravel would otherwise pluralise this to "staffs". */
    protected $table = 'staff';

    public const ROLES = [
        'isp_admin'  => 'ISP Admin',
        'lco'        => 'LCO Manager',
        'technician' => 'Technician',
        'support'    => 'Support Agent',
        'accounts'   => 'Accounts',
        'sales'      => 'Sales',
    ];

    public const EMPLOYMENT_TYPES = [
        'full_time' => 'Full Time',
        'part_time' => 'Part Time',
        'contract'  => 'Contract',
        'intern'    => 'Intern',
    ];

    public const STATUSES = [
        'active'     => 'Active',
        'on_leave'   => 'On Leave',
        'suspended'  => 'Suspended',
        'resigned'   => 'Resigned',
        'terminated' => 'Terminated',
    ];

    protected $fillable = [
        'tenant_id', 'user_id', 'franchise_id', 'reports_to_id',
        'code', 'name', 'designation', 'department', 'role',
        'email', 'phone', 'emergency_contact',
        'date_of_birth', 'date_of_joining', 'date_of_leaving', 'employment_type',
        'address', 'city', 'state', 'pincode',
        'pan_number', 'aadhaar_number',
        'bank_account_name', 'bank_account_number', 'bank_ifsc',
        'basic_salary', 'hra', 'other_allowances',
        'pf_percent', 'esi_percent', 'professional_tax', 'overtime_rate_per_hour',
        'status', 'notes',
    ];

    protected $casts = [
        'date_of_birth'          => 'date',
        'date_of_joining'        => 'date',
        'date_of_leaving'        => 'date',
        'basic_salary'           => 'float',
        'hra'                    => 'float',
        'other_allowances'       => 'float',
        'pf_percent'             => 'float',
        'esi_percent'            => 'float',
        'professional_tax'       => 'float',
        'overtime_rate_per_hour' => 'float',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function franchise(): BelongsTo { return $this->belongsTo(Franchise::class); }

    public function manager(): BelongsTo { return $this->belongsTo(self::class, 'reports_to_id'); }

    public function reports(): HasMany { return $this->hasMany(self::class, 'reports_to_id'); }

    public function attendance(): HasMany { return $this->hasMany(Attendance::class, 'staff_id'); }

    public function payslips(): HasMany { return $this->hasMany(Payslip::class, 'staff_id'); }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(StaffGroup::class, 'staff_group_members', 'staff_id', 'staff_group_id')
            ->withTimestamps();
    }

    /** Tickets this member owns (primary assignee). */
    public function ownedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_staff_id');
    }

    /** Every ticket this member is on, owner or collaborator. */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assignees', 'staff_id', 'ticket_id')
            ->withPivot(['is_primary', 'assigned_at'])
            ->withTimestamps();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function employmentTypeLabel(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->employment_type));
    }

    /** Full monthly cost to company before deductions. */
    public function grossSalary(): float
    {
        return round((float) $this->basic_salary + (float) $this->hra + (float) $this->other_allowances, 2);
    }

    /** An employee can be rostered / assigned work only while employed. */
    public function isAssignable(): bool
    {
        return in_array($this->status, ['active', 'on_leave'], true);
    }

    /**
     * Next staff code for the tenant: ST-0001. Used when the operator leaves
     * the code blank on the create form.
     */
    public static function nextCode(int|string $tenantId): string
    {
        $last = self::where('tenant_id', $tenantId)
            ->where('code', 'like', 'ST-%')
            ->orderByDesc('code')
            ->first();

        $seq = 1;
        if ($last && preg_match('/ST-(\d+)/', $last->code, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('ST-%04d', $seq);
    }
}
