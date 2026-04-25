<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Stadium;
use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use App\Http\Resources\v1\StadiumResource;
use App\Http\Resources\v1\StadiumDetailResource;
use App\Http\Resources\v1\FieldResource;

class StadiumController extends Controller
{
    #[OA\Get(
        path: "/v1/public/stadiums",
        summary: "List all stadiums for a tenant",
        tags: ["Public"]
    )]
    #[OA\Parameter(
        name: "X-Tenant-Slug",
        in: "header",
        required: true,
        description: "Tenant slug (e.g. 'zain')",
        schema: new OA\Schema(type: "string")
    )]
    #[OA\Parameter(name: "city", in: "query", description: "Filter by city", schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "sport_type", in: "query", description: "Filter by sport type", schema: new OA\Schema(type: "string"))]
    #[OA\Response(
        response: 200,
        description: "Successful operation",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object"))
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Tenant not found")]
    public function index(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $query = Stadium::with(['activeFields'])
            ->where('tenant_id', $tenant->id)
            ->active();

        // فلترة بالمدينة
        if ($request->city) {
            $query->where('city', $request->city);
        }

        // فلترة بالقرب (lat, lng, radius_km)
        if ($request->lat && $request->lng) {
            $radius = $request->radius ?? 10;
            $query->nearby($request->lat, $request->lng, $radius);
        }

        // فلترة بنوع الرياضة
        if ($request->sport_type) {
            $query->whereHas('fields', function ($q) use ($request) {
                $q->where('sport_type', $request->sport_type)->where('is_active', true);
            });
        }

        // فلترة بالتاريخ والوقت (ملاعب بها slots متاحة)
        if ($request->date) {
            $query->whereHas('fields', function ($q) use ($request) {
                $q->where('is_active', true)
                  ->whereDoesntHave('bookings', function ($bq) use ($request) {
                      // تبسيط: نظهر الملاعب التي لها على الأقل slot واحدة متاحة
                      $bq->whereDate('booking_date', $request->date)
                         ->whereIn('status', ['confirmed', 'pending']);
                  });
            });
        }

        $stadiums = $query->orderByDesc('is_featured')
                          ->withCount(['activeFields as active_fields_count'])
                          ->orderBy('name')
                          ->paginate($request->per_page ?? 15);

        return StadiumResource::collection($stadiums)->additional(['success' => true]);
    }

    #[OA\Get(
        path: "/v1/public/stadiums/{slug}",
        summary: "Get stadium details",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "slug", in: "path", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 404, description: "Stadium or Tenant not found")]
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant  = app('tenant');
        $stadium = Stadium::with(['activeFields.pricingRules'])
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();

        return (new StadiumDetailResource($stadium))->additional(['success' => true]);
    }

    #[OA\Get(
        path: "/v1/public/stadiums/{slug}/fields",
        summary: "Get stadium fields with prices",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "slug", in: "path", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "sport_type", in: "query", schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function fields(Request $request, string $slug): JsonResponse
    {
        $tenant  = app('tenant');
        $stadium = Stadium::where('tenant_id', $tenant->id)
                          ->where('slug', $slug)
                          ->firstOrFail();

        $fields = $stadium->activeFields()
            ->with('pricingRules')
            ->when($request->sport_type, fn($q) => $q->ofSport($request->sport_type))
            ->when($request->size, fn($q) => $q->where('size', $request->size))
            ->get();

        return FieldResource::collection($fields)->additional(['success' => true]);
    }

    #[OA\Get(
        path: "/v1/public/fields/{id}/slots",
        summary: "Get available slots for a specific date",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "date", in: "query", required: true, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function availableSlots(Request $request, int $id): JsonResponse
    {
        $request->validate(['date' => 'required|date|after_or_equal:today']);

        $tenant = app('tenant');
        $field  = Field::whereHas('stadium', fn($q) => $q->where('tenant_id', $tenant->id))
                       ->findOrFail($id);

        $date = $request->date;
        $cacheKey = "field_{$id}_slots_{$date}";

        $slots = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($field, $date) {
            return $field->getAvailableSlots($date);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'field'     => ['id' => $field->id, 'name' => $field->name],
                'date'      => $date,
                'slots'     => $slots,
                'currency'  => $field->currency,
            ],
        ]);
    }

    #[OA\Get(
        path: "/v1/public/cities",
        summary: "Get list of available cities",
        tags: ["Public"]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function cities(): JsonResponse
    {
        $tenant = app('tenant');
        $cities = Stadium::where('tenant_id', $tenant->id)
                         ->active()
                         ->distinct()
                         ->pluck('city')
                         ->sort()
                         ->values();

        return response()->json(['success' => true, 'data' => $cities]);
    }

    // Manual resource methods removed as they are replaced by JsonResource classes
}
