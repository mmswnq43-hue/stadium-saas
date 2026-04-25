<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * استخدام: Route::middleware('role:owner,manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذا المورد',
                'code'    => 'FORBIDDEN',
            ], 403);
        }

        // للمالك والمدير: تحقق أنه ينتمي لنفس الـ tenant
        if (in_array($user->role, ['owner', 'manager'])) {
            $tenant = app('tenant');
            if ($tenant && $user->tenant_id !== $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية على هذا الحساب',
                    'code'    => 'TENANT_MISMATCH',
                ], 403);
            }
        }

        return $next($request);
    }
}
