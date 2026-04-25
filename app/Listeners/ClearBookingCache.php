<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Events\BookingConfirmed;
use App\Events\BookingCancelled;
use Illuminate\Support\Facades\Cache;

class ClearBookingCache
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $booking = $event->booking;
        $tenantId = $booking->tenant_id;
        $fieldId  = $booking->field_id;
        $date     = $booking->booking_date->format('Y-m-d');

        // 1. مسح كاش إحصائيات لوحة التحكم للمستأجر
        Cache::forget("tenant_{$tenantId}_dashboard_stats");

        // 2. مسح كاش الأوقات المتاحة لهذا الملعب في هذا التاريخ
        Cache::forget("field_{$fieldId}_slots_{$date}");
    }
}
