<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\Stadium;
use App\Models\PricingRule;
use App\Models\BlockedSlot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\Owner\StoreFieldRequest;
use App\Http\Resources\v1\FieldResource;
use OpenApi\Attributes as OA;

class FieldController extends Controller
{
    #[OA\Get(
        path: "/v1/owner/stadiums/{stadiumId}/fields",
        summary: "List all fields for a stadium",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "stadiumId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "List of fields")]
    public function index(int $stadiumId): JsonResponse
    {
        $tenant  = app('tenant');
        $fields = Field::with(['pricingRules'])
            ->where('stadium_id', $stadiumId)
            ->where('tenant_id', $tenant->id)
            ->get();

        return FieldResource::collection($fields)->additional(['success' => true]);
    }

    #[OA\Post(
        path: "/v1/owner/stadiums/{stadiumId}/fields",
        summary: "Create a new field in a stadium",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "stadiumId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "sport_type", "size", "capacity", "surface_type", "price_per_hour"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Pitch A"),
                new OA\Property(property: "sport_type", type: "string", example: "football"),
                new OA\Property(property: "size", type: "string", example: "5x5"),
                new OA\Property(property: "capacity", type: "integer", example: 10),
                new OA\Property(property: "surface_type", type: "string", example: "artificial_grass"),
                new OA\Property(property: "price_per_hour", type: "number", example: 150)
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Field created")]
    public function store(StoreFieldRequest $request, int $stadiumId): JsonResponse
    {
        $tenant  = app('tenant');
        $stadium = Stadium::where('tenant_id', $tenant->id)->findOrFail($stadiumId);

        $field = Field::create(array_merge(
            $request->validated(),
            ['tenant_id' => $tenant->id, 'stadium_id' => $stadium->id]
        ));

        return (new FieldResource($field->load('pricingRules')))
            ->additional(['success' => true, 'message' => 'تم إضافة الملعب الفرعي'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Put(
        path: "/v1/owner/fields/{id}",
        summary: "Update field details",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: "name", type: "string")]))]
    #[OA\Response(response: 200, description: "Field updated")]
    public function update(StoreFieldRequest $request, int $id): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::where('tenant_id', $tenant->id)->findOrFail($id);

        $field->update($request->validated());

        return (new FieldResource($field->fresh(['pricingRules'])))
            ->additional(['success' => true, 'message' => 'تم تحديث الملعب']);
    }

    /**
     * DELETE /api/owner/fields/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::where('tenant_id', $tenant->id)->findOrFail($id);

        $hasUpcoming = $field->bookings()->upcoming()->exists();
        if ($hasUpcoming) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف ملعب له حجوزات قادمة',
            ], 422);
        }

        $field->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الملعب الفرعي']);
    }

    // ==================== Pricing Rules ====================

    #[OA\Get(
        path: "/owner/fields/{id}/pricing-rules",
        summary: "Get pricing rules for a field",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "List of rules")]
    public function pricingRules(int $id): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::where('tenant_id', $tenant->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $field->pricingRules()->orderByDesc('priority')->get(),
        ]);
    }

    #[OA\Post(
        path: "/owner/fields/{id}/pricing-rules",
        summary: "Create a pricing rule",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "type", "price", "price_type"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Weekend Special"),
                new OA\Property(property: "type", type: "string", enum: ["time_based", "day_based", "date_range"]),
                new OA\Property(property: "price", type: "number", example: 200),
                new OA\Property(property: "price_type", type: "string", enum: ["fixed", "percentage_increase"])
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Rule created")]
    public function storePricingRule(Request $request, int $id): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::where('tenant_id', $tenant->id)->findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:time_based,day_based,date_range,special',
            'days_of_week'=> 'nullable|array',
            'days_of_week.*' => 'integer|between:0,6',
            'start_time'  => 'nullable|date_format:H:i',
            'end_time'    => 'nullable|date_format:H:i|after:start_time',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
            'price'       => 'required|numeric|min:0',
            'price_type'  => 'required|in:fixed,percentage_increase,percentage_decrease',
            'priority'    => 'sometimes|integer|min:0',
        ]);

        $rule = PricingRule::create(array_merge(
            $request->validated(),
            ['tenant_id' => $tenant->id, 'field_id' => $field->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة قاعدة التسعير',
            'data'    => $rule,
        ], 201);
    }

    #[OA\Delete(
        path: "/owner/pricing-rules/{ruleId}",
        summary: "Delete a pricing rule",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "ruleId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Rule deleted")]
    public function deletePricingRule(int $ruleId): JsonResponse
    {
        $tenant = app('tenant');
        $rule   = PricingRule::where('tenant_id', $tenant->id)->findOrFail($ruleId);
        $rule->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف قاعدة التسعير']);
    }

    // ==================== Blocked Slots ====================

    #[OA\Post(
        path: "/owner/fields/{id}/block",
        summary: "Block a specific time slot",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["date", "start_time", "end_time"],
            properties: [
                new OA\Property(property: "date", type: "string", format: "date", example: "2024-12-01"),
                new OA\Property(property: "start_time", type: "string", example: "10:00"),
                new OA\Property(property: "end_time", type: "string", example: "12:00"),
                new OA\Property(property: "reason", type: "string"),
                new OA\Property(property: "is_full_day", type: "boolean")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Slot blocked")]
    public function blockSlot(Request $request, int $id): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::where('tenant_id', $tenant->id)->findOrFail($id);

        $request->validate([
            'date'       => 'required|date|after_or_equal:today',
            'start_time' => 'required_unless:is_full_day,true|date_format:H:i',
            'end_time'   => 'required_unless:is_full_day,true|date_format:H:i|after:start_time',
            'reason'     => 'nullable|string|max:200',
            'is_full_day'=> 'sometimes|boolean',
        ]);

        $blocked = BlockedSlot::create([
            'tenant_id'  => $tenant->id,
            'field_id'   => $field->id,
            'date'       => $request->date,
            'start_time' => $request->is_full_day ? '00:00' : $request->start_time,
            'end_time'   => $request->is_full_day ? '23:59' : $request->end_time,
            'reason'     => $request->reason,
            'is_full_day'=> $request->is_full_day ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حجب الوقت بنجاح',
            'data'    => $blocked,
        ], 201);
    }

    #[OA\Get(
        path: "/owner/fields/{id}/blocked-slots",
        summary: "Get blocked slots for a field",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "date", in: "query", schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Response(response: 200, description: "List of blocked slots")]
    public function blockedSlots(Request $request, int $id): JsonResponse
    {
        $tenant = app('tenant');
        $field  = Field::where('tenant_id', $tenant->id)->findOrFail($id);

        $slots = $field->blockedSlots()
            ->when($request->date, fn($q) => $q->where('date', $request->date))
            ->when($request->month, fn($q) => $q->whereMonth('date', $request->month))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json(['success' => true, 'data' => $slots]);
    }

    #[OA\Delete(
        path: "/owner/blocked-slots/{slotId}",
        summary: "Unblock a slot",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "slotId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Slot unblocked")]
    public function unblockSlot(int $slotId): JsonResponse
    {
        $tenant = app('tenant');
        BlockedSlot::where('tenant_id', $tenant->id)->findOrFail($slotId)->delete();

        return response()->json(['success' => true, 'message' => 'تم إلغاء الحجب']);
    }
}
