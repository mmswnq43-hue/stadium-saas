<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Field;
use App\Models\DiscountCoupon;
use App\Models\Tenant;
use App\Events\BookingCreated;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * التحقق من توفر الملعب
     */
    public function checkAvailability(Field $field, string $date, string $startTime, string $endTime): array
    {
        // تحقق من أن التاريخ في المستقبل
        $bookingStart = Carbon::parse("$date $startTime");
        if ($bookingStart->isPast()) {
            return ['available' => false, 'reason' => trans('messages.booking.past_date')];
        }

        // تحقق من أوقات الملعب
        $stadium = $field->stadium;
        if (!$stadium->isOpenAt($startTime) || !$stadium->isOpenAt($endTime)) {
            return [
                'available' => false,
                'reason'    => trans('messages.booking.closed'),
            ];
        }

        // تحقق من الأيام المفتوحة
        if (!$stadium->is_open_today && !empty($stadium->working_days)) {
            $day = Carbon::parse($date)->dayOfWeek;
            if (!in_array($day, $stadium->working_days)) {
                return ['available' => false, 'reason' => trans('messages.booking.closed')];
            }
        }

        // تحقق من مدة الحجز
        $start    = Carbon::parse("$date $startTime");
        $end      = Carbon::parse("$date $endTime");
        $duration = $end->diffInMinutes($start);

        if ($duration < $field->min_booking_duration) {
            return [
                'available' => false,
                'reason'    => trans('messages.booking.min_duration', ['minutes' => $field->min_booking_duration]),
            ];
        }

        if ($duration > $field->max_booking_duration) {
            return [
                'available' => false,
                'reason'    => trans('messages.booking.max_duration', ['minutes' => $field->max_booking_duration]),
            ];
        }

        // تحقق من وجود حجز متعارض
        $conflict = Booking::where('field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('start_time', '<=', $startTime)
                         ->where('end_time', '>=', $endTime);
                  });
            })
            ->exists();

        if ($conflict) {
            return ['available' => false, 'reason' => trans('messages.booking.unavailable')];
        }

        // تحقق من الأوقات المحجوبة
        $blocked = $field->blockedSlots()
            ->where('date', $date)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('is_full_day', true)
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('start_time', '<', $endTime)
                         ->where('end_time', '>', $startTime);
                  });
            })
            ->exists();

        if ($blocked) {
            return ['available' => false, 'reason' => trans('messages.booking.unavailable')];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * حساب سعر الحجز مع الخصومات
     */
    public function calculateBookingPrice(
        Field $field,
        string $date,
        string $startTime,
        string $endTime,
        ?string $couponCode = null
    ): array {
        $pricing  = $field->calculatePrice($date, $startTime, $endTime);
        $subtotal = $pricing['subtotal'];

        $discountAmount = 0;
        $coupon         = null;

        if ($couponCode) {
            $coupon = $this->validateCoupon($couponCode, $field->tenant_id, $subtotal);
            if ($coupon['valid']) {
                $discountAmount = $coupon['discount_amount'];
            }
        }

        $afterDiscount = $subtotal - $discountAmount;
        $taxRate       = $field->tenant->getSetting('tax_rate', 15); // VAT 15% default
        $taxAmount     = round($afterDiscount * ($taxRate / 100), 2);
        $total         = $afterDiscount + $taxAmount;

        return [
            'price_per_hour'   => $pricing['price_per_hour'],
            'duration_minutes' => $pricing['duration_minutes'],
            'subtotal'         => $subtotal,
            'discount_amount'  => $discountAmount,
            'discount_code'    => $couponCode,
            'tax_rate'         => $taxRate,
            'tax_amount'       => $taxAmount,
            'total_amount'     => round($total, 2),
            'currency'         => $field->currency,
            'rule_applied'     => $pricing['rule_applied'],
            'coupon'           => $coupon,
        ];
    }

    /**
     * إنشاء الحجز
     */
    public function createBooking(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $field   = Field::findOrFail($data['field_id']);
            $stadium = $field->stadium;

            // التحقق من التوفر مرة أخرى داخل transaction
            $availability = $this->checkAvailability(
                $field,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            );

            if (!$availability['available']) {
                throw new \Exception($availability['reason']);
            }

            // حساب السعر
            $pricing = $this->calculateBookingPrice(
                $field,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time'],
                $data['discount_code'] ?? null
            );

            $booking = Booking::create([
                'tenant_id'        => $field->tenant_id,
                'stadium_id'       => $stadium->id,
                'field_id'         => $field->id,
                'user_id'          => $data['user_id'] ?? null,
                'customer_name'    => $data['customer_name'],
                'customer_phone'   => $data['customer_phone'],
                'customer_email'   => $data['customer_email'] ?? null,
                'booking_date'     => $data['booking_date'],
                'start_time'       => $data['start_time'],
                'end_time'         => $data['end_time'],
                'duration_minutes' => $pricing['duration_minutes'],
                'price_per_hour'   => $pricing['price_per_hour'],
                'subtotal'         => $pricing['subtotal'],
                'discount_amount'  => $pricing['discount_amount'],
                'discount_code'    => $pricing['discount_code'],
                'tax_amount'       => $pricing['tax_amount'],
                'total_amount'     => $pricing['total_amount'],
                'currency'         => $pricing['currency'],
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'customer_notes'   => $data['customer_notes'] ?? null,
                'source'           => $data['source'] ?? 'web',
            ]);

            // تحديث عداد الكوبون
            if ($pricing['discount_code'] && $pricing['coupon']['valid']) {
                DiscountCoupon::where('code', $pricing['discount_code'])
                    ->increment('usage_count');
            }

            $booking->load(['field.stadium', 'tenant']);

            event(new BookingCreated($booking));

            return $booking;
        });
    }

    /**
     * التحقق من صلاحية الكوبون
     */
    private function validateCoupon(string $code, int $tenantId, float $subtotal): array
    {
        $coupon = DiscountCoupon::where('code', $code)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'كود الخصم غير صحيح', 'discount_amount' => 0];
        }

        if ($coupon->valid_from && now()->lt($coupon->valid_from)) {
            return ['valid' => false, 'message' => 'كود الخصم لم يبدأ بعد', 'discount_amount' => 0];
        }

        if ($coupon->valid_until && now()->gt($coupon->valid_until)) {
            return ['valid' => false, 'message' => 'انتهت صلاحية كود الخصم', 'discount_amount' => 0];
        }

        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            return ['valid' => false, 'message' => 'تم استخدام كود الخصم بالكامل', 'discount_amount' => 0];
        }

        if ($coupon->min_booking_amount && $subtotal < $coupon->min_booking_amount) {
            return [
                'valid'   => false,
                'message' => "الحد الأدنى للحجز {$coupon->min_booking_amount} للاستفادة من الخصم",
                'discount_amount' => 0,
            ];
        }

        $discount = $coupon->type === 'percentage'
            ? $subtotal * ($coupon->value / 100)
            : $coupon->value;

        if ($coupon->max_discount_amount) {
            $discount = min($discount, $coupon->max_discount_amount);
        }

        return [
            'valid'           => true,
            'message'         => 'تم تطبيق الخصم بنجاح',
            'discount_amount' => round($discount, 2),
            'coupon_id'       => $coupon->id,
        ];
    }
}
