<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'email', 'phone', 'logo',
        'plan', 'status', 'trial_ends_at', 'subscription_ends_at', 'settings',
    ];

    protected $casts = [
        'settings'             => 'array',
        'trial_ends_at'        => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    // ===================== Relations =====================

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function stadiums(): HasMany
    {
        return $this->hasMany(Stadium::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ===================== Accessors =====================

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' ||
            ($this->status === 'trial' && $this->trial_ends_at?->isFuture());
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    // ===================== Scopes =====================

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'active')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'trial')
                     ->where('trial_ends_at', '>', now());
              });
        });
    }

    // ===================== Methods =====================

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getMaxStadiums(): int
    {
        return match ($this->plan) {
            'basic'        => 1,
            'professional' => 5,
            'enterprise'   => PHP_INT_MAX,
            default        => 1,
        };
    }

    public function getMaxFields(): int
    {
        return match ($this->plan) {
            'basic'        => 5,
            'professional' => 20,
            'enterprise'   => PHP_INT_MAX,
            default        => 5,
        };
    }
}
