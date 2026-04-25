<?php

namespace App\Jobs;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CompleteFinishedBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = now();
        
        $bookings = Booking::where('status', 'confirmed')
            ->where(function ($query) use ($now) {
                $query->whereDate('booking_date', '<', $now->toDateString())
                      ->orWhere(function ($q) use ($now) {
                          $q->whereDate('booking_date', $now->toDateString())
                            ->where('end_time', '<=', $now->toTimeString());
                      });
            })
            ->get();

        foreach ($bookings as $booking) {
            $booking->update(['status' => 'completed']);
            Log::info("Booking #{$booking->booking_number} marked as completed by system.");
        }
    }
}
