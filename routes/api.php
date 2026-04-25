<?php

use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\Api\Auth\AuthController;

// Public Controllers
use App\Http\Controllers\Api\Public\StadiumController as PublicStadiumController;
use App\Http\Controllers\Api\Public\BookingController  as PublicBookingController;

// Owner Controllers
use App\Http\Controllers\Api\Owner\StadiumController   as OwnerStadiumController;
use App\Http\Controllers\Api\Owner\FieldController     as OwnerFieldController;
use App\Http\Controllers\Api\Owner\BookingController   as OwnerBookingController;
use App\Http\Controllers\Api\Owner\DashboardController as OwnerDashboardController;

// Admin Controllers
use App\Http\Controllers\Api\Admin\TenantController    as AdminTenantController;

Route::prefix('v1')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | 1. Authentication Routes (بدون tenant middleware)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::get('profile',          [AuthController::class, 'profile']);
            Route::put('profile',          [AuthController::class, 'updateProfile']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | 2. Public Routes (مع tenant middleware - للعملاء)
    |--------------------------------------------------------------------------
    */
    Route::middleware('tenant')->prefix('public')->group(function () {

        // استعراض الملاعب
        Route::get('cities',                                [PublicStadiumController::class, 'cities']);
        Route::get('stadiums',                              [PublicStadiumController::class, 'index']);
        Route::get('stadiums/{slug}',                       [PublicStadiumController::class, 'show']);
        Route::get('stadiums/{slug}/fields',                [PublicStadiumController::class, 'fields']);
        Route::get('fields/{id}/slots',                     [PublicStadiumController::class, 'availableSlots']);

        // الحجوزات - عامة
        Route::post('bookings/check-availability',          [PublicBookingController::class, 'checkAvailability']);
        Route::post('bookings',                             [PublicBookingController::class, 'store'])->middleware('throttle:booking');
        Route::get('bookings/track/{bookingNumber}',        [PublicBookingController::class, 'track']);
        Route::post('bookings/{bookingNumber}/cancel',      [PublicBookingController::class, 'cancel']);

        // الحجوزات - للمستخدمين المسجلين فقط
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('bookings/my',                       [PublicBookingController::class, 'myBookings']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | 3. Owner Routes (مالك الملاعب)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'tenant', 'role:owner,manager'])->prefix('owner')->group(function () {

        // لوحة التحكم والتقارير
        Route::get('dashboard',             [OwnerDashboardController::class, 'index']);
        Route::get('reports/revenue',       [OwnerDashboardController::class, 'revenueReport']);

        // إدارة الملاعب الرئيسية
        Route::apiResource('stadiums', OwnerStadiumController::class)->except(['show']);

        // إدارة الملاعب الفرعية
        Route::get('stadiums/{stadiumId}/fields',   [OwnerFieldController::class, 'index']);
        Route::post('stadiums/{stadiumId}/fields',  [OwnerFieldController::class, 'store']);
        Route::put('fields/{id}',                  [OwnerFieldController::class, 'update']);
        Route::delete('fields/{id}',               [OwnerFieldController::class, 'destroy']);

        // قواعد التسعير
        Route::get('fields/{id}/pricing-rules',    [OwnerFieldController::class, 'pricingRules']);
        Route::post('fields/{id}/pricing-rules',   [OwnerFieldController::class, 'storePricingRule']);
        Route::delete('pricing-rules/{ruleId}',    [OwnerFieldController::class, 'deletePricingRule']);

        // حجب الأوقات
        Route::get('fields/{id}/blocked-slots',    [OwnerFieldController::class, 'blockedSlots']);
        Route::post('fields/{id}/block',           [OwnerFieldController::class, 'blockSlot']);
        Route::delete('blocked-slots/{slotId}',    [OwnerFieldController::class, 'unblockSlot']);

        // إدارة الحجوزات
        Route::get('bookings',                     [OwnerBookingController::class, 'index']);
        Route::post('bookings',                    [OwnerBookingController::class, 'store']);
        Route::get('bookings/calendar',            [OwnerBookingController::class, 'calendar']);
        Route::get('bookings/{id}',                [OwnerBookingController::class, 'show']);
        Route::patch('bookings/{id}/confirm',      [OwnerBookingController::class, 'confirm']);
        Route::patch('bookings/{id}/cancel',       [OwnerBookingController::class, 'cancel']);
        Route::patch('bookings/{id}/complete',     [OwnerBookingController::class, 'complete']);
        Route::patch('bookings/{id}/payment',      [OwnerBookingController::class, 'recordPayment']);
    });

    /*
    |--------------------------------------------------------------------------
    | 4. Super Admin Routes (لا يحتاج tenant middleware)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {

        // إدارة المستأجرين
        Route::get('tenants',                      [AdminTenantController::class, 'index']);
        Route::post('tenants',                     [AdminTenantController::class, 'store']);
        Route::get('tenants/{id}',                 [AdminTenantController::class, 'show']);
        Route::patch('tenants/{id}/status',        [AdminTenantController::class, 'updateStatus']);
        Route::patch('tenants/{id}/plan',          [AdminTenantController::class, 'updatePlan']);

        // إحصائيات المنصة
        Route::get('stats',                        [AdminTenantController::class, 'platformStats']);
    });
});
