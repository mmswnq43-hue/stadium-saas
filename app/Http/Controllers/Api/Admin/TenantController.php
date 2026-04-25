<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class TenantController extends Controller
{
    #[OA\Get(
        path: "/v1/admin/tenants",
        summary: "List all tenants (Super Admin)",
        tags: ["Admin"],
        security: [["sanctum" => []]]
    )]
    #[OA\Response(response: 200, description: "List of tenants")]
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::withCount(['stadiums', 'fields', 'bookings', 'users'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->plan,   fn($q) => $q->where('plan', $request->plan))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $tenants->map(fn($t) => $this->tenantResource($t)),
            'meta'    => ['total' => $tenants->total(), 'last_page' => $tenants->lastPage()],
        ]);
    }

    #[OA\Post(
        path: "/v1/admin/tenants",
        summary: "Create a new tenant/owner account",
        tags: ["Admin"],
        security: [["sanctum" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["tenant_name", "tenant_email", "tenant_phone", "plan", "owner_name", "owner_email", "owner_password"],
            properties: [
                new OA\Property(property: "tenant_name", type: "string"),
                new OA\Property(property: "tenant_email", type: "string", format: "email"),
                new OA\Property(property: "plan", type: "string", enum: ["basic", "professional", "enterprise"]),
                new OA\Property(property: "owner_name", type: "string"),
                new OA\Property(property: "owner_email", type: "string"),
                new OA\Property(property: "owner_password", type: "string")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Tenant and Owner created")]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_name'  => 'required|string|max:100',
            'tenant_email' => 'required|email|unique:tenants,email',
            'tenant_phone' => 'required|string|max:20',
            'plan'         => 'required|in:basic,professional,enterprise',
            'owner_name'   => 'required|string|max:100',
            'owner_email'  => 'required|email|unique:users,email',
            'owner_password'=> 'required|string|min:8',
            'trial_days'   => 'sometimes|integer|min:0|max:90',
        ]);

        return DB::transaction(function () use ($request) {
            $slug    = Str::slug($request->tenant_name);
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = Str::slug($request->tenant_name) . '-' . $counter++;
            }

            $trialDays = $request->trial_days ?? 14;

            $tenant = Tenant::create([
                'name'          => $request->tenant_name,
                'slug'          => $slug,
                'email'         => $request->tenant_email,
                'phone'         => $request->tenant_phone,
                'plan'          => $request->plan,
                'status'        => $trialDays > 0 ? 'trial' : 'active',
                'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
            ]);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $request->owner_name,
                'email'     => $request->owner_email,
                'password'  => $request->owner_password,
                'role'      => 'owner',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء حساب المالك بنجاح',
                'data'    => [
                    'tenant' => $this->tenantResource($tenant),
                    'owner'  => ['id' => $owner->id, 'email' => $owner->email],
                ],
            ], 201);
        });
    }

    #[OA\Get(
        path: "/v1/admin/tenants/{id}",
        summary: "Get tenant details and stats",
        tags: ["Admin"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Tenant details")]
    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::withCount(['stadiums', 'fields', 'bookings', 'users'])
            ->with(['stadiums:id,tenant_id,name,city,is_active'])
            ->findOrFail($id);

        // إحصائيات مالية
        $revenueStats = Booking::where('tenant_id', $id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->selectRaw('COUNT(*) as total_bookings, SUM(total_amount) as total_revenue')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->tenantResource($tenant), [
                'stadiums'       => $tenant->stadiums,
                'total_bookings' => $revenueStats->total_bookings,
                'total_revenue'  => $revenueStats->total_revenue,
            ]),
        ]);
    }

    #[OA\Patch(
        path: "/v1/admin/tenants/{id}/status",
        summary: "Update tenant status",
        tags: ["Admin"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["status"],
            properties: [
                new OA\Property(property: "status", type: "string", enum: ["active", "suspended", "trial"])
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Status updated")]
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,suspended,trial',
            'reason' => 'nullable|string',
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => $request->status]);

        $message = match ($request->status) {
            'active'    => 'تم تفعيل الحساب',
            'suspended' => 'تم تعليق الحساب',
            'trial'     => 'تم تحويل الحساب لتجريبي',
        };

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * PATCH /api/admin/tenants/{id}/plan
     * ترقية / تخفيض الباقة
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'plan'                  => 'required|in:basic,professional,enterprise',
            'subscription_ends_at'  => 'nullable|date|after:today',
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update([
            'plan'                  => $request->plan,
            'subscription_ends_at'  => $request->subscription_ends_at,
            'status'                => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "تم تحديث الباقة إلى {$request->plan}",
        ]);
    }

    #[OA\Get(
        path: "/v1/admin/stats",
        summary: "Get platform-wide statistics",
        tags: ["Admin"],
        security: [["sanctum" => []]]
    )]
    #[OA\Response(response: 200, description: "Platform stats")]
    public function platformStats(): JsonResponse
    {
        $stats = [
            'tenants' => [
                'total'     => Tenant::count(),
                'active'    => Tenant::where('status', 'active')->count(),
                'trial'     => Tenant::where('status', 'trial')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
                'by_plan'   => Tenant::select('plan', DB::raw('COUNT(*) as count'))
                    ->groupBy('plan')->pluck('count', 'plan'),
            ],
            'bookings' => [
                'total'       => Booking::count(),
                'this_month'  => Booking::whereMonth('created_at', now()->month)->count(),
                'revenue'     => Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_amount'),
                'this_month_revenue' => Booking::whereIn('status', ['confirmed', 'completed'])
                    ->whereMonth('booking_date', now()->month)->sum('total_amount'),
            ],
            'infrastructure' => [
                'stadiums' => \App\Models\Stadium::count(),
                'fields'   => \App\Models\Field::count(),
                'users'    => User::where('role', '!=', 'super_admin')->count(),
            ],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    private function tenantResource(Tenant $tenant): array
    {
        return [
            'id'                    => $tenant->id,
            'name'                  => $tenant->name,
            'slug'                  => $tenant->slug,
            'domain'                => $tenant->domain,
            'email'                 => $tenant->email,
            'phone'                 => $tenant->phone,
            'plan'                  => $tenant->plan,
            'status'                => $tenant->status,
            'trial_ends_at'         => $tenant->trial_ends_at?->toDateString(),
            'subscription_ends_at'  => $tenant->subscription_ends_at?->toDateString(),
            'is_active'             => $tenant->is_active,
            'stadiums_count'        => $tenant->stadiums_count ?? null,
            'fields_count'          => $tenant->fields_count ?? null,
            'bookings_count'        => $tenant->bookings_count ?? null,
            'users_count'           => $tenant->users_count ?? null,
            'max_stadiums'          => $tenant->getMaxStadiums(),
            'max_fields'            => $tenant->getMaxFields(),
            'created_at'            => $tenant->created_at->toDateString(),
        ];
    }
}
