<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\Public\StoreBookingRequest;
use App\Http\Resources\v1\BookingResource;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    #[OA\Post(
        path: "/v1/public/bookings/check-availability",
        summary: "Check field availability and calculate price",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["field_id", "date", "start_time", "end_time"],
            properties: [
                new OA\Property(property: "field_id", type: "integer", example: 1),
                new OA\Property(property: "date", type: "string", format: "date", example: "2024-12-01"),
                new OA\Property(property: "start_time", type: "string", example: "18:00"),
                new OA\Property(property: "end_time", type: "string", example: "19:00"),
                new OA\Property(property: "discount_code", type: "string", example: "SAVE10")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Availability results")]
    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'field_id'   => 'required|integer|exists:fields,id',
            'date'       => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
        ]);

        $tenant = app('tenant');
        $field  = Field::whereHas('stadium', fn($q) => $q->where('tenant_id', $tenant->id))
                       ->where('is_active', true)
                       ->findOrFail($request->field_id);

        $result = $this->bookingService->checkAvailability(
            $field, $request->date, $request->start_time, $request->end_time
        );

        if ($result['available']) {
            $pricing = $this->bookingService->calculateBookingPrice(
                $field, $request->date, $request->start_time, $request->end_time,
                $request->discount_code
            );
            $result['pricing'] = $pricing;
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    #[OA\Post(
        path: "/v1/public/bookings",
        summary: "Create a new booking",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["field_id", "date", "start_time", "end_time", "customer_name", "customer_phone"],
            properties: [
                new OA\Property(property: "field_id", type: "integer", example: 1),
                new OA\Property(property: "date", type: "string", format: "date", example: "2024-12-01"),
                new OA\Property(property: "start_time", type: "string", example: "18:00"),
                new OA\Property(property: "end_time", type: "string", example: "19:00"),
                new OA\Property(property: "customer_name", type: "string", example: "Ahmed Ali"),
                new OA\Property(property: "customer_phone", type: "string", example: "0501234567"),
                new OA\Property(property: "customer_email", type: "string", format: "email"),
                new OA\Property(property: "discount_code", type: "string"),
                new OA\Property(property: "customer_notes", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Booking created")]
    #[OA\Response(response: 422, description: "Validation error or unavailable")]
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::whereHas('stadium', fn($q) => $q->where('tenant_id', $tenant->id))
                       ->where('is_active', true)
                       ->findOrFail($request->field_id);

        try {
            $booking = $this->bookingService->createBooking($request->validated());

            return (new BookingResource($booking))
                ->additional(['success' => true, 'message' => 'تم إنشاء الحجز بنجاح'])
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    #[OA\Get(
        path: "/v1/public/bookings/track/{bookingNumber}",
        summary: "Track booking status by number",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "booking_number", in: "path", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Booking details")]
    public function track(Request $request, string $bookingNumber): JsonResponse
    {
        $tenant  = app('tenant');
        $booking = Booking::with(['field.stadium'])
            ->where('tenant_id', $tenant->id)
            ->where('booking_number', $bookingNumber)
            ->firstOrFail();

        return (new BookingResource($booking))->additional(['success' => true]);
    }

    #[OA\Post(
        path: "/v1/public/bookings/{bookingNumber}/cancel",
        summary: "Cancel booking by customer",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "booking_number", in: "path", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "reason", type: "string", example: "Changed my mind")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Booking cancelled")]
    public function cancel(Request $request, string $bookingNumber): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant  = app('tenant');
        $booking = Booking::where('tenant_id', $tenant->id)
                          ->where('booking_number', $bookingNumber)
                          ->firstOrFail();

        if (!$booking->can_be_cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الحجز',
            ], 422);
        }

        $booking->cancel('user', $request->reason ?? '');

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الحجز بنجاح',
        ]);
    }

    #[OA\Get(
        path: "/public/my-bookings",
        summary: "Get logged-in user bookings",
        tags: ["Public"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "List of user bookings")]
    public function myBookings(Request $request): JsonResponse
    {
        $user     = $request->user();
        $tenant   = app('tenant');

        $bookings = Booking::with(['field.stadium'])
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('booking_date')
            ->paginate(10);

        return BookingResource::collection($bookings)->additional(['success' => true]);
    }

}
