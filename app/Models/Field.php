<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'stadium_id', 'name', 'sport_type', 'size',
        'dimensions', 'capacity', 'surface_type',
        'price_per_hour', 'price_weekday', 'price_weekend',
        'price_morning', 'price_evening', 'currency',
        'min_booking_duration', 'max_booking_duration', 'booking_slot_duration',
        'has_lighting', 'is_covered', 'has_ac', 'features',
        'is_active', 'image', 'images', 'notes', 'sort_order',
    ];

    protected $casts = [
        'price_per_hour'        => 'decimal:2',
        'price_weekday'         => 'decimal:2',
        'price_weekend'         => 'decimal:2',
        'price_morning'         => 'decimal:2',
        'price_evening'         => 'decimal:2',
        'has_lighting'          => 'boolean',
        'is_covered'            => 'boolean',
        'has_ac'                => 'boolean',
        'is_active'             => 'boolean',
        'features'              => 'array',
        'images'                => 'array',
    ];

    // ===================== Relations =====================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class)->orderByDesc('priority');
    }

    public function blockedSlots(): HasMany
    {
        return $this->hasMany(BlockedSlot::class);
    }

    // ===================== Scopes =====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfSport($query, string $sport)
    {
        return $query->where('sport_type', $sport);
    }

    // ===================== Accessors =====================

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function getImagesUrlsAttribute(): array
    {
        if (!$this->images) return [];
        return array_map(fn($img) => asset('storage/' . $img), $this->images);
    }

    public function getSportTypeLabelAttribute(): string
    {
        return match ($this->sport_type) {
            'football'    => 'كرة قدم',
            'basketball'  => 'كرة سلة',
            'volleyball'  => 'كرة طائرة',
            'tennis'      => 'تنس',
            'padel'       => 'بادل',
            'squash'      => 'اسكواش',
            'badminton'   => 'ريشة طائرة',
            'cricket'     => 'كريكيت',
            'futsal'      => 'فوتسال',
            'multi_sport' => 'متعدد الرياضات',
            default       => $this->sport_type,
        };
    }

    // ===================== Methods =====================

    /**
     * احتساب سعر الحجز بناءً على الوقت والتاريخ
     */
    public function calculatePrice(string $date, string $startTime, string $endTime): array
    {
        $start    = \Carbon\Carbon::parse("$date $startTime");
        $end      = \Carbon\Carbon::parse("$date $endTime");
        $minutes  = $end->diffInMinutes($start);
        $hours    = $minutes / 60;

        // ابحث عن pricing rule مناسبة
        $rule = $this->pricingRules()
            ->where('is_active', true)
            ->get()
            ->first(function ($r) use ($start, $date) {
                if ($r->type === 'day_based') {
                    return in_array($start->dayOfWeek, $r->days_of_week ?? []);
                }
                if ($r->type === 'time_based') {
                    return $start->format('H:i') >= $r->start_time &&
                           $start->format('H:i') < $r->end_time;
                }
                if ($r->type === 'date_range') {
                    return $date >= $r->date_from && $date <= $r->date_to;
                }
                return false;
            });

        $basePrice = $this->price_per_hour;

        if ($rule) {
            $basePrice = match ($rule->price_type) {
                'fixed'               => $rule->price,
                'percentage_increase' => $basePrice * (1 + $rule->value / 100),
                'percentage_decrease' => $basePrice * (1 - $rule->value / 100),
                default               => $basePrice,
            };
        }

        $subtotal = round($basePrice * $hours, 2);

        return [
            'price_per_hour' => $basePrice,
            'duration_hours' => $hours,
            'duration_minutes' => $minutes,
            'subtotal'       => $subtotal,
            'rule_applied'   => $rule?->name,
        ];
    }

    /**
     * جلب الـ slots المتاحة لتاريخ معين
     */
    public function getAvailableSlots(string $date): array
    {
        $stadium = $this->stadium;
        $slots   = [];
        $slotMin = $this->booking_slot_duration;

        $openH  = \Carbon\Carbon::parse("$date {$stadium->opens_at}");
        $closeH = \Carbon\Carbon::parse("$date {$stadium->closes_at}");

        // الحجوزات الموجودة
        $bookedSlots = $this->bookings()
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get(['start_time', 'end_time']);

        // الأوقات المحجوبة
        $blocked = $this->blockedSlots()
            ->where('date', $date)
            ->get();

        $current = clone $openH;
        while ($current->copy()->addMinutes($slotMin)->lte($closeH)) {
            $slotStart = $current->format('H:i');
            $slotEnd   = $current->copy()->addMinutes($slotMin)->format('H:i');

            $isBooked  = $bookedSlots->contains(function ($b) use ($slotStart, $slotEnd) {
                return $b->start_time < $slotEnd && $b->end_time > $slotStart;
            });

            $isBlocked = $blocked->contains(function ($b) use ($slotStart, $slotEnd) {
                if ($b->is_full_day) return true;
                return $b->start_time < $slotEnd && $b->end_time > $slotStart;
            });

            $pricing = $this->calculatePrice($date, $slotStart, $slotEnd);

            $slots[] = [
                'start_time'  => $slotStart,
                'end_time'    => $slotEnd,
                'is_available'=> !$isBooked && !$isBlocked,
                'price'       => $pricing['subtotal'],
                'currency'    => $this->currency,
            ];

            $current->addMinutes($slotMin);
        }

        return $slots;
    }
}
