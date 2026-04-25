<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stadium extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description',
        'country', 'city', 'district', 'address', 'latitude', 'longitude', 'google_maps_url',
        'phone', 'whatsapp', 'email',
        'opens_at', 'closes_at', 'working_days',
        'is_active', 'is_featured', 'amenities',
    ];

    protected $casts = [
        'latitude'     => 'decimal:8',
        'longitude'    => 'decimal:8',
        'working_days' => 'array',
        'amenities'    => 'array',
        'is_active'    => 'boolean',
        'is_featured'  => 'boolean',
    ];

    // ===================== Relations =====================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function activeFields(): HasMany
    {
        return $this->hasMany(Field::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // ===================== Accessors =====================

    public function getIsOpenTodayAttribute(): bool
    {
        if (empty($this->working_days)) return true;
        $today = now()->dayOfWeek; // 0=Sunday
        return in_array($today, $this->working_days);
    }

    public function getDistanceAttribute(): ?float
    {
        return $this->attributes['distance'] ?? null;
    }

    // ===================== Scopes =====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    /**
     * البحث بالقرب من موقع محدد (بالكيلومترات)
     */
    public function scopeNearby($query, float $lat, float $lng, float $radius = 10)
    {
        return $query->selectRaw("*, 
            (6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(latitude))
            )) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<=', $radius)
            ->orderBy('distance');
    }

    // ===================== Methods =====================

    public function isOpenAt(string $time): bool
    {
        return $time >= $this->opens_at && $time <= $this->closes_at;
    }
}
