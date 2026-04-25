<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password',
        'role', 'is_active', 'avatar',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ===================== Relations =====================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ===================== Helpers =====================

    public function isSuperAdmin(): bool  { return $this->role === 'super_admin'; }
    public function isOwner(): bool       { return $this->role === 'owner'; }
    public function isManager(): bool     { return $this->role === 'manager'; }
    public function isCustomer(): bool    { return $this->role === 'customer'; }

    public function canManageTenant(int $tenantId): bool
    {
        return $this->isSuperAdmin() ||
               (in_array($this->role, ['owner', 'manager']) && $this->tenant_id === $tenantId);
    }
}
