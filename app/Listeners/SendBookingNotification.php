<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Events\BookingConfirmed;
use App\Events\BookingCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendBookingNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $booking = $event->booking;

        if ($event instanceof BookingCreated) {
            $this->handleCreated($booking);
        } elseif ($event instanceof BookingConfirmed) {
            $this->handleConfirmed($booking);
        } elseif ($event instanceof BookingCancelled) {
            $this->handleCancelled($booking);
        }
    }

    private function handleCreated($booking)
    {
        Log::info("Notification: New Booking #{$booking->booking_number} created for {$booking->customer_name}.");
        // هنا يتم استدعاء SMS gateway أو Mail Service
    }

    private function handleConfirmed($booking)
    {
        Log::info("Notification: Booking #{$booking->booking_number} confirmed. Notify customer: {$booking->customer_phone}.");
    }

    private function handleCancelled($booking)
    {
        Log::info("Notification: Booking #{$booking->booking_number} cancelled. Reason: {$booking->cancellation_reason}.");
    }
}
