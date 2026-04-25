<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Stadium;
use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/v1/owner/dashboard",
        summary: "Get owner dashboard statistics",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "Dashboard statistics")]
    public function index(Request $request): JsonResponse
    {
        $tenant  = app('tenant');
        $tenantId = $tenant->id;

        $cacheKey = "tenant_{$tenantId}_dashboard_stats";

        $stats = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($tenantId) {
            $today     = today();
            $thisMonth = now()->startOfMonth();
            $lastMonth = now()->subMonth()->startOfMonth();

            // ---- إحصائيات اليوم ----
            $todayBookings = Booking::forTenant($tenantId)
                ->whereDate('booking_date', $today)
                ->whereIn('status', ['confirmed', 'pending', 'completed'])
                ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue')
                ->first();

            // ---- إحصائيات الشهر الحالي ----
            $thisMonthStats = Booking::forTenant($tenantId)
                ->whereDate('booking_date', '>=', $thisMonth)
                ->whereIn('status', ['confirmed', 'completed'])
                ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue, SUM(discount_amount) as discounts')
                ->first();

            // ---- إحصائيات الشهر الماضي ----
            $lastMonthStats = Booking::forTenant($tenantId)
                ->whereDate('booking_date', '>=', $lastMonth)
                ->whereDate('booking_date', '<', $thisMonth)
                ->whereIn('status', ['confirmed', 'completed'])
                ->selectRaw('COUNT(*) as count, SUM(total_amount) as revenue')
                ->first();

            // ---- الحجوزات القادمة (7 أيام) ----
            $upcomingCount = Booking::forTenant($tenantId)
                ->upcoming()
                ->whereDate('booking_date', '<=', today()->addDays(7))
                ->count();

            // ---- في الانتظار ----
            $pendingCount = Booking::forTenant($tenantId)
                ->where('status', 'pending')
                ->count();

            // ---- أكثر الملاعب حجزاً ----
            $topFields = Booking::forTenant($tenantId)
                ->whereDate('booking_date', '>=', $thisMonth)
                ->whereIn('status', ['confirmed', 'completed'])
                ->select('field_id', DB::raw('COUNT(*) as bookings_count, SUM(total_amount) as revenue'))
                ->groupBy('field_id')
                ->with('field:id,name,sport_type')
                ->orderByDesc('bookings_count')
                ->limit(5)
                ->get()
                ->map(fn($b) => [
                    'field_id'      => $b->field_id,
                    'field_name'    => $b->field->name ?? 'Unknown',
                    'sport_type'    => $b->field->sport_type_label ?? 'Unknown',
                    'bookings_count'=> $b->bookings_count,
                    'revenue'       => (float) $b->revenue,
                ]);

            // ---- إيرادات آخر 30 يوم (يومياً) ----
            $dailyRevenue = Booking::forTenant($tenantId)
                ->whereDate('booking_date', '>=', today()->subDays(29))
                ->whereIn('status', ['confirmed', 'completed'])
                ->select(
                    DB::raw('DATE(booking_date) as date'),
                    DB::raw('COUNT(*) as bookings'),
                    DB::raw('SUM(total_amount) as revenue')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // ---- توزيع الحجوزات حسب نوع الرياضة ----
            $sportDistribution = Booking::forTenant($tenantId)
                ->whereDate('booking_date', '>=', $thisMonth)
                ->whereIn('status', ['confirmed', 'completed'])
                ->join('fields', 'bookings.field_id', '=', 'fields.id')
                ->select('fields.sport_type', DB::raw('COUNT(*) as count'))
                ->groupBy('fields.sport_type')
                ->get();

            // ---- نسبة الإلغاء ----
            $totalThisMonth   = Booking::forTenant($tenantId)->whereDate('booking_date', '>=', $thisMonth)->count();
            $cancelledThisMonth = Booking::forTenant($tenantId)->whereDate('booking_date', '>=', $thisMonth)->where('status', 'cancelled')->count();
            $cancellationRate = $totalThisMonth > 0 ? round(($cancelledThisMonth / $totalThisMonth) * 100, 1) : 0;

            return [
                'today' => [
                    'bookings' => (int) $todayBookings->count,
                    'revenue'  => (float) $todayBookings->revenue,
                ],
                'this_month' => [
                    'bookings'  => (int) $thisMonthStats->count,
                    'revenue'   => (float) $thisMonthStats->revenue,
                    'discounts' => (float) $thisMonthStats->discounts,
                ],
                'last_month' => [
                    'bookings' => (int) $lastMonthStats->count,
                    'revenue'  => (float) $lastMonthStats->revenue,
                ],
                'growth' => [
                    'bookings_pct' => $lastMonthStats->count > 0
                        ? round((($thisMonthStats->count - $lastMonthStats->count) / $lastMonthStats->count) * 100, 1)
                        : 100,
                    'revenue_pct'  => $lastMonthStats->revenue > 0
                        ? round((($thisMonthStats->revenue - $lastMonthStats->revenue) / $lastMonthStats->revenue) * 100, 1)
                        : 100,
                ],
                'pending_bookings'  => $pendingCount,
                'upcoming_bookings' => $upcomingCount,
                'cancellation_rate' => $cancellationRate,
                'top_fields'        => $topFields,
                'daily_revenue'     => $dailyRevenue,
                'sport_distribution'=> $sportDistribution,
                'infrastructure'    => [
                    'stadiums' => Stadium::where('tenant_id', $tenantId)->count(),
                    'fields'   => Field::where('tenant_id', $tenantId)->count(),
                    'active_fields' => Field::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                ],
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }

    #[OA\Get(
        path: "/v1/owner/reports/revenue",
        summary: "Get revenue report",
        tags: ["Owner"],
        security: [["sanctum" => []]]
    )]
    #[OA\Parameter(name: "X-Tenant-Slug", in: "header", required: true, schema: new OA\Schema(type: "string"))]
    #[OA\Parameter(name: "from", in: "query", required: true, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "to", in: "query", required: true, schema: new OA\Schema(type: "string", format: "date"))]
    #[OA\Parameter(name: "group_by", in: "query", schema: new OA\Schema(type: "string", enum: ["day", "week", "month"]))]
    #[OA\Response(response: 200, description: "Revenue data")]
    public function revenueReport(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => 'required|date',
            'to'       => 'required|date|after_or_equal:from',
            'group_by' => 'sometimes|in:day,week,month',
        ]);

        $tenant   = app('tenant');
        $groupBy  = $request->group_by ?? 'month';

        $dateFormat = match ($groupBy) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            'month' => '%Y-%m',
        };

        $data = Booking::forTenant($tenant->id)
            ->whereDate('booking_date', '>=', $request->from)
            ->whereDate('booking_date', '<=', $request->to)
            ->whereIn('status', ['confirmed', 'completed'])
            ->select(
                DB::raw("DATE_FORMAT(booking_date, '$dateFormat') as period"),
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(subtotal) as subtotal'),
                DB::raw('SUM(discount_amount) as discounts'),
                DB::raw('SUM(tax_amount) as tax'),
                DB::raw('SUM(total_amount) as revenue'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
