<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named team of staff (e.g. "Field Technicians — North").
 *
 * Exists so a ticket can be handed to a whole team in one action; the members
 * are expanded into `ticket_assignees` at assignment time.
 */
class StaffGroup extends Model
{
    protected $table = 'staff_groups';

    protected $fillable = ['tenant_id', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_group_members', 'staff_group_id', 'staff_id')
            ->withTimestamps();
    }

    /** Only members who may still be given work (SRD §5.4 status ladder). */
    public function assignableMembers()
    {
        return $this->members()->whereIn('status', ['active', 'on_leave'])->get();
    }
}
