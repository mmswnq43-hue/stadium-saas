<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: "/v1/auth/register",
        summary: "Register a new customer",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["name", "email", "phone", "password", "password_confirmation"],
            properties: [
                new OA\Property(property: "name", type: "string", example: "Ahmed Ali"),
                new OA\Property(property: "email", type: "string", format: "email", example: "ahmed@example.com"),
                new OA\Property(property: "phone", type: "string", example: "0501234567"),
                new OA\Property(property: "password", type: "string", format: "password", example: "password123"),
                new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "password123")
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "User registered successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "message", type: "string", example: "تم إنشاء الحساب بنجاح"),
                new OA\Property(property: "data", type: "object")
            ]
        )
    )]
    #[OA\Response(response: 422, description: "Validation error")]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'password'  => $request->password,
            'role'      => 'customer',
            'tenant_id' => null, // العملاء ليسوا مرتبطين بـ tenant محدد
        ]);

        $token = $user->createToken('customer')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح',
            'data'    => [
                'user'  => $this->userResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    #[OA\Post(
        path: "/v1/auth/login",
        summary: "User login",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "ahmed@example.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "password123")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Login successful",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "message", type: "string", example: "تم تسجيل الدخول بنجاح"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "user", type: "object"),
                    new OA\Property(property: "token", type: "string")
                ])
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Invalid credentials")]
    #[OA\Response(response: 403, description: "Account inactive")]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'بيانات تسجيل الدخول غير صحيحة',
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'تم تعطيل هذا الحساب، تواصل مع الدعم',
            ], 403);
        }

        $user->tokens()->delete(); // إلغاء الـ tokens القديمة
        $token = $user->createToken($user->role)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data'    => [
                'user'  => $this->userResource($user),
                'token' => $token,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="User logout",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تسجيل الخروج بنجاح")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    /**
     * GET /api/auth/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'success' => true,
            'data'    => $this->userResource($user),
        ]);
    }

    /**
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name'  => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
        ]);

        $user->update($request->only('name', 'phone'));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي',
            'data'    => $this->userResource($user),
        ]);
    }

    private function userResource(User $user): array
    {
        return [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'phone'  => $user->phone,
            'role'   => $user->role,
            'tenant' => $user->tenant ? [
                'id'   => $user->tenant->id,
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
                'plan' => $user->tenant->plan,
            ] : null,
        ];
    }
}
