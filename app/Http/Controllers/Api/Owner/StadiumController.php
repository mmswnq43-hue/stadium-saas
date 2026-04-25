<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Stadium;
use App\Models\Field;
use App\Models\Booking;
use App\Models\BlockedSlot;
use App\Models\PricingRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\Owner\StoreStadiumRequest;
use App\Http\Resources\v1\StadiumResource;
use OpenApi\Attributes as OA;

class StadiumController extends Controller
{
    #[OA\Get(
        path: "/v1/owner/stadiums",
        summary: "List all stadiums by owner",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "List of stadiums")]
    public function index(Request $request): JsonResponse
    {
        $tenant   = app('tenant');
        $stadiums = Stadium::withCount(['activeFields as active_fields_count', 'bookings'])
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return StadiumResource::collection($stadiums)->additional(['success' => true]);
    }

    #[OA\Post(
        path: "/v1/owner/stadiums",
        summary: "Create a new stadium",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "city", "address", "opens_at", "closes_at"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Al-Hilal Stadium"),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(property: "city", type: "string", example: "Riyadh"),
                new OA\Property(property: "address", type: "string", example: "Olaya Street"),
                new OA\Property(property: "opens_at", type: "string", example: "08:00"),
                new OA\Property(property: "closes_at", type: "string", example: "23:00")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Stadium created")]
    #[OA\Response(response: 403, description: "Plan limit reached")]
    public function store(StoreStadiumRequest $request): JsonResponse
    {
        $tenant = app('tenant');

        // تحقق من حد الـ plan
        $current = Stadium::where('tenant_id', $tenant->id)->count();
        if ($current >= $tenant->getMaxStadiums()) {
            return response()->json([
                'success' => false,
                'message' => "خطة {$tenant->plan} تسمح بحد أقصى {$tenant->getMaxStadiums()} ملعب رئيسي",
            ], 403);
        }

        return DB::transaction(function () use ($request, $tenant) {
            $slug    = Str::slug($request->name);
            $counter = 1;
            while (Stadium::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
                $slug = Str::slug($request->name) . '-' . $counter++;
            }

            $stadium = Stadium::create(array_merge(
                $request->validated(),
                ['tenant_id' => $tenant->id, 'slug' => $slug]
            ));

            return (new StadiumResource($stadium))
                ->additional(['success' => true, 'message' => 'تم إنشاء الملعب بنجاح'])
                ->response()
                ->setStatusCode(201);
        });
    }

    #[OA\Put(
        path: "/v1/owner/stadiums/{id}",
        summary: "Update stadium details",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "is_active", type: "boolean")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Stadium updated")]
    public function update(StoreStadiumRequest $request, int $id): JsonResponse
    {
        $tenant  = app('tenant');
        $stadium = Stadium::where('tenant_id', $tenant->id)->findOrFail($id);

        $stadium->update($request->validated());

        return (new StadiumResource($stadium->fresh()))
            ->additional(['success' => true, 'message' => 'تم تحديث الملعب']);
    }

    #[OA\Delete(
        path: "/v1/owner/stadiums/{id}",
        summary: "Delete a stadium",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Stadium deleted")]
    #[OA\Response(response: 422, description: "Cannot delete stadium with upcoming bookings")]
    public function destroy(int $id): JsonResponse
    {
        $tenant  = app('tenant');
        $stadium = Stadium::where('tenant_id', $tenant->id)->findOrFail($id);

        // تحقق من عدم وجود حجوزات قادمة
        $hasUpcoming = Booking::where('stadium_id', $id)
            ->upcoming()
            ->exists();

        if ($hasUpcoming) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف ملعب له حجوزات قادمة',
            ], 422);
        }

        $stadium->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الملعب']);
    }
}
