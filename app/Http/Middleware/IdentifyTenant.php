<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * كشف الـ Tenant من:
     * 1. subdomain: zain.stadiums.com
     * 2. X-Tenant-Slug header
     * 3. tenant_slug في query string
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found.',
                'code'    => 'TENANT_NOT_FOUND',
            ], 404);
        }

        if (!$tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This account is suspended or trial has expired.',
                'code'    => 'TENANT_SUSPENDED',
            ], 403);
        }

        // ضع الـ tenant في الـ request لاستخدامه في Controllers
        $request->attributes->set('tenant', $tenant);
        app()->instance('tenant', $tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        // 1. من الـ Header
        if ($slug = $request->header('X-Tenant-Slug')) {
            return Tenant::active()->where('slug', $slug)->first();
        }

        // 2. من الـ Query String
        if ($slug = $request->query('tenant')) {
            return Tenant::active()->where('slug', $slug)->first();
        }

        // 3. من الـ Subdomain
        $host   = $request->getHost();
        $parts  = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            return Tenant::active()
                ->where('slug', $subdomain)
                ->orWhere('domain', $host)
                ->first();
        }

        return null;
    }
}
