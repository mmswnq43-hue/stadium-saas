<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Events\BookingConfirmed;
use App\Events\BookingCancelled;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_number', 'tenant_id', 'stadium_id', 'field_id', 'user_id',
        'customer_name', 'customer_phone', 'customer_email',
        'booking_date', 'start_time', 'end_time', 'duration_minutes',
        'price_per_hour', 'subtotal', 'discount_amount', 'discount_code',
        'tax_amount', 'total_amount', 'currency',
        'status', 'payment_status', 'payment_method', 'payment_reference', 'paid_at',
        'customer_notes', 'admin_notes', 'cancelled_by', 'cancellation_reason', 'cancelled_at',
        'source',
    ];

    protected $casts = [
        'booking_date'    => 'date',
        'price_per_hour'  => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'paid_at'         => 'datetime',
        'cancelled_at'    => 'datetime',
    ];

    // ===================== Boot =====================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($booking) {
            if (!$booking->booking_number) {
                $booking->booking_number = self::generateBookingNumber($booking->tenant_id);
            }
        });
    }

    // ===================== Relations =====================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===================== Scopes =====================

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('booking_date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', today())
                     ->whereIn('status', ['confirmed', 'pending'])
                     ->orderBy('booking_date')
                     ->orderBy('start_time');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ===================== Accessors =====================

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'في الانتظار',
            'confirmed' => 'مؤكد',
            'cancelled' => 'ملغى',
            'completed' => 'مكتمل',
            'no_show'   => 'لم يحضر',
            default     => $this->status,
        };
    }

    public function getCanBeCancelledAttribute(): bool
    {
        if (!in_array($this->status, ['pending', 'confirmed'])) return false;
        $bookingDateTime = \Carbon\Carbon::parse("{$this->booking_date} {$this->start_time}");
        return $bookingDateTime->isFuture() && $bookingDateTime->diffInHours(now()) >= 2;
    }

    // ===================== Methods =====================

    private static function generateBookingNumber(int $tenantId): string
    {
        $year  = now()->format('Y');
        $count = self::where('tenant_id', $tenantId)
                     ->whereYear('created_at', $year)
                     ->count() + 1;
        return sprintf('BK-%s-%05d', $year, $count);
    }

    public function confirm(): bool
    {
        if ($this->status !== 'pending') return false;
        
        $updated = $this->update(['status' => 'confirmed']);
        if ($updated) {
            event(new BookingConfirmed($this));
        }
        return $updated;
    }

    public function cancel(string $cancelledBy, string $reason = ''): bool
    {
        $updated = $this->update([
            'status'              => 'cancelled',
            'cancelled_by'        => $cancelledBy,
            'cancellation_reason' => $reason,
            'cancelled_at'        => now(),
        ]);

        if ($updated) {
            event(new BookingCancelled($this));
        }

        return $updated;
    }

    public function markAsPaid(string $method, ?string $reference = null): bool
    {
        return $this->update([
            'payment_status'    => 'paid',
            'payment_method'    => $method,
            'payment_reference' => $reference,
            'paid_at'           => now(),
        ]);
    }
}
