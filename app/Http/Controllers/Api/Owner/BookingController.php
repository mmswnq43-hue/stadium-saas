<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use App\Http\Resources\v1\BookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    #[OA\Get(
        path: "/v1/owner/bookings",
        summary: "List all bookings for a tenant",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "List of bookings")]
    public function index(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $bookings = Booking::with(['field.stadium'])
            ->where('tenant_id', $tenant->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date, fn($q) => $q->whereDate('booking_date', $request->date))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sq) use ($request) {
                    $sq->where('customer_name', 'like', "%{$request->search}%")
                       ->orWhere('customer_phone', 'like', "%{$request->search}%")
                       ->orWhere('booking_number', 'like', "%{$request->search}%");
                });
            })
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->paginate($request->per_page ?? 15);

        return BookingResource::collection($bookings)->additional(['success' => true])->response()->getData(true);
    }

    #[OA\Get(
        path: "/v1/owner/bookings/{id}",
        summary: "Get booking details",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Booking details")]
    public function show(int $id): JsonResponse
    {
        $tenant  = app('tenant');
        $booking = Booking::with(['field.stadium', 'user'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        return (new BookingResource($booking))->additional(['success' => true])->response()->getData(true);
    }

    #[OA\Post(
        path: "/owner/bookings",
        summary: "Store a manual booking (Admin/Owner)",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["field_id", "date", "start_time", "end_time", "customer_name", "customer_phone"],
            properties: [
                new OA\Property(property: "field_id", type: "integer", example: 1),
                new OA\Property(property: "date", type: "string", format: "date"),
                new OA\Property(property: "start_time", type: "string", example: "18:00"),
                new OA\Property(property: "end_time", type: "string", example: "19:00"),
                new OA\Property(property: "customer_name", type: "string", example: "John Doe"),
                new OA\Property(property: "customer_phone", type: "string", example: "0500000000")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Booking created and confirmed")]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'field_id'       => 'required|integer|exists:fields,id',
            'date'           => 'required|date',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i|after:start_time',
            'customer_name'  => 'required|string|max:100',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email',
            'admin_notes'    => 'nullable|string',
            'source'         => 'sometimes|in:admin,walk_in,phone',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,wallet',
            'is_paid'        => 'sometimes|boolean',
        ]);

        try {
            $booking = $this->bookingService->createBooking(array_merge(
                $request->all(),
                ['source' => 'owner', 'status' => 'confirmed']
            ));

            return (new BookingResource($booking->load('field.stadium')))
                ->additional(['success' => true, 'message' => 'تم إنشاء الحجز وتأكيده'])
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    #[OA\Patch(
        path: "/v1/owner/bookings/{id}/confirm",
        summary: "Confirm a pending booking",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Booking confirmed")]
    public function confirm(int $id): JsonResponse
    {
        $tenant  = app('tenant');
        $booking = Booking::where('tenant_id', $tenant->id)->findOrFail($id);

        return DB::transaction(function () use ($booking) {
            $booking->confirm();

            return (new BookingResource($booking))
                ->additional(['success' => true, 'message' => 'تم تأكيد الحجز']);
        });
    }

    #[OA\Patch(
        path: "/v1/owner/bookings/{id}/cancel",
        summary: "Cancel a booking",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: "reason", type: "string")]))]
    #[OA\Response(response: 200, description: "Booking cancelled")]
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $tenant  = app('tenant');
        $booking = Booking::where('tenant_id', $tenant->id)->findOrFail($id);

        return DB::transaction(function () use ($booking, $request) {
            $booking->cancel('owner', $request->reason ?? '');

            return (new BookingResource($booking))
                ->additional(['success' => true, 'message' => 'تم إلغاء الحجز']);
        });
    }

    /**
     * PATCH /api/owner/bookings/{id}/complete
     */
    public function complete(int $id): JsonResponse
    {
        $tenant  = app('tenant');
        $booking = Booking::where('tenant_id', $tenant->id)
            ->where('status', 'confirmed')
            ->findOrFail($id);

        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'completed']);

            return (new BookingResource($booking))
                ->additional(['success' => true, 'message' => 'تم تأكيد اكتمال الحجز']);
        });
    }

    #[OA\Patch(
        path: "/v1/owner/bookings/{id}/payment",
        summary: "Record payment for a booking",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["payment_method"],
            properties: [
                new OA\Property(property: "payment_method", type: "string", enum: ["cash", "card", "online"]),
                new OA\Property(property: "payment_reference", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Payment recorded")]
    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'payment_method'    => 'required|in:cash,card,bank_transfer,online,wallet',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $tenant  = app('tenant');
        $booking = Booking::where('tenant_id', $tenant->id)->findOrFail($id);

        $booking->markAsPaid($request->payment_method, $request->payment_reference);

        return response()->json(['success' => true, 'message' => 'تم تسجيل الدفع بنجاح']);
    }

    #[OA\Get(
        path: "/owner/bookings/calendar",
        summary: "Get bookings for calendar view",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "date", in: "query", required: true, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "field_id", in: "query", schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Calendar events")]
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'date'     => 'required|date',
            'field_id' => 'nullable|integer|exists:fields,id',
        ]);

        $tenant = app('tenant');

        $query = Booking::with(['field'])
            ->where('tenant_id', $tenant->id)
            ->whereDate('booking_date', $request->date)
            ->whereIn('status', ['confirmed', 'pending']);

        if ($request->field_id) {
            $query->where('field_id', $request->field_id);
        }

        $bookings = $query->orderBy('start_time')->get();

        return response()->json([
            'success' => true,
            'data'    => $bookings->map(fn($b) => [
                'id'             => $b->id,
                'booking_number' => $b->booking_number,
                'field_id'       => $b->field_id,
                'field_name'     => $b->field->name,
                'customer_name'  => $b->customer_name,
                'customer_phone' => $b->customer_phone,
                'start_time'     => $b->start_time,
                'end_time'       => $b->end_time,
                'status'         => $b->status,
                'payment_status' => $b->payment_status,
                'total_amount'   => $b->total_amount,
            ]),
        ]);
    }
}
