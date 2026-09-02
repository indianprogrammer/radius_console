<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'name', 'username', 'email', 'password', 'role', 'franchise_id', 'staff_id', 'subscriber_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public const ROLES = [
        'superadmin'  => 'Super Admin',
        'admin'       => 'ISP Admin',
        'franchise'   => 'Franchise',
        'staff'       => 'Staff',
        'subscriber'  => 'Subscriber',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function franchise(): BelongsTo { return $this->belongsTo(Franchise::class); }
    public function staffMember(): BelongsTo { return $this->belongsTo(Staff::class, 'staff_id'); }
    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }
}
