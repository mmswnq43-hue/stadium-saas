# توثيق API - نظام حجوزات الملاعب (SaaS)

## نظرة عامة على البنية

```
المنصة (SaaS)
├── Super Admin       → يدير جميع حسابات مالكي الملاعب
├── Tenant (مالك)     → يملك ملاعب رئيسية وفرعية
│   ├── Stadium       → الملعب الرئيسي (موقع + معلومات)
│   │   └── Field[]   → الملاعب الفرعية (نوع + حجم + سعر)
│   └── Bookings      → الحجوزات
└── Customer          → العميل الذي يحجز
```

## تعريف الـ Tenant

كل طلب API يحتاج تعريف الـ tenant بإحدى الطرق:

| الطريقة | المثال |
|---------|--------|
| Header | `X-Tenant-Slug: zain-sports` |
| Subdomain | `zain-sports.yourdomain.com` |
| Query String | `?tenant=zain-sports` |

---

## 1. المصادقة (Auth)

### تسجيل عميل جديد
```
POST /api/auth/register

Body:
{
    "name": "محمد العتيبي",
    "email": "m@example.com",
    "phone": "+966555000001",
    "password": "Pass@1234",
    "password_confirmation": "Pass@1234"
}

Response 201:
{
    "success": true,
    "data": {
        "user": { "id": 1, "name": "...", "role": "customer" },
        "token": "1|abc..."
    }
}
```

### تسجيل الدخول
```
POST /api/auth/login

Body:
{
    "email": "owner@zainsports.sa",
    "password": "Owner@1234"
}
```

### الاستخدام بعد تسجيل الدخول
```
Authorization: Bearer {token}
```

---

## 2. الملاعب العامة (Public)

> جميع الطلبات تحتاج: `X-Tenant-Slug: {slug}`

### استعراض الملاعب
```
GET /api/public/stadiums

Query Parameters (اختياري):
  city=الرياض
  sport_type=football
  lat=24.7136&lng=46.6753&radius=10   ← البحث بالقرب (كيلومتر)
  date=2024-12-20
  per_page=15

Response:
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "ملاعب زين - حي النخيل",
            "slug": "zain-nakheel",
            "city": "الرياض",
            "address": "شارع النخيل...",
            "latitude": 24.7136,
            "longitude": 46.6753,
            "google_maps": "https://...",
            "opens_at": "06:00",
            "closes_at": "24:00",
            "amenities": ["parking", "wifi", "cafeteria"],
            "fields_count": 4,
            "sport_types": ["football", "padel", "basketball"],
            "price_from": 120,
            "distance": 2.3  ← (إذا تم إرسال lat/lng)
        }
    ],
    "meta": { "total": 1, "current_page": 1, "last_page": 1 }
}
```

### تفاصيل ملعب مع ملاعبه الفرعية
```
GET /api/public/stadiums/{slug}

مثال: GET /api/public/stadiums/zain-nakheel

Response:
{
    "success": true,
    "data": {
        "id": 1,
        "name": "ملاعب زين - حي النخيل",
        "description": "...",
        "fields": [
            {
                "id": 1,
                "name": "ملعب كرة قدم A (5×5)",
                "sport_type": "football",
                "sport_type_label": "كرة قدم",
                "size": "5x5",
                "capacity": 10,
                "surface_type": "artificial_grass",
                "price_per_hour": 150,
                "price_weekend": 200,
                "currency": "SAR",
                "has_lighting": true,
                "is_covered": false,
                "features": ["balls", "bibs"],
                "pricing_rules": [
                    {
                        "name": "سعر المساء",
                        "type": "time_based",
                        "start_time": "19:00",
                        "end_time": "24:00",
                        "price": 180
                    }
                ]
            }
        ]
    }
}
```

### الأوقات المتاحة لملعب في تاريخ معين
```
GET /api/public/fields/{id}/slots?date=2024-12-20

Response:
{
    "success": true,
    "data": {
        "field": { "id": 1, "name": "ملعب كرة قدم A" },
        "date": "2024-12-20",
        "slots": [
            { "start_time": "06:00", "end_time": "07:00", "is_available": true,  "price": 150, "currency": "SAR" },
            { "start_time": "07:00", "end_time": "08:00", "is_available": false, "price": 150, "currency": "SAR" },
            { "start_time": "19:00", "end_time": "20:00", "is_available": true,  "price": 180, "currency": "SAR" }
        ]
    }
}
```

---

## 3. الحجوزات (Public)

### التحقق من التوفر والسعر
```
POST /api/public/bookings/check-availability
Headers: X-Tenant-Slug: zain-sports

Body:
{
    "field_id": 1,
    "date": "2024-12-20",
    "start_time": "18:00",
    "end_time": "20:00",
    "discount_code": "WELCOME10"   (اختياري)
}

Response:
{
    "success": true,
    "data": {
        "available": true,
        "reason": null,
        "pricing": {
            "price_per_hour": 180,
            "duration_minutes": 120,
            "subtotal": 360,
            "discount_amount": 36,
            "discount_code": "WELCOME10",
            "tax_rate": 15,
            "tax_amount": 48.6,
            "total_amount": 372.6,
            "currency": "SAR",
            "rule_applied": "سعر المساء (بعد 7م)"
        }
    }
}
```

### إنشاء حجز
```
POST /api/public/bookings
Headers: X-Tenant-Slug: zain-sports

Body:
{
    "field_id": 1,
    "date": "2024-12-20",
    "start_time": "18:00",
    "end_time": "20:00",
    "customer_name": "محمد العتيبي",
    "customer_phone": "+966555000001",
    "customer_email": "m@example.com",
    "discount_code": "WELCOME10",
    "customer_notes": "أرجو توفير الكرات"
}

Response 201:
{
    "success": true,
    "message": "تم إنشاء الحجز بنجاح، في انتظار التأكيد",
    "data": {
        "booking_number": "BK-2024-00001",
        "status": "pending",
        "status_label": "في الانتظار",
        "field": { "name": "ملعب كرة قدم A", "sport_type": "كرة قدم" },
        "booking_date": "2024-12-20",
        "start_time": "18:00",
        "end_time": "20:00",
        "pricing": {
            "subtotal": 360,
            "discount_amount": 36,
            "tax_amount": 48.6,
            "total_amount": 372.6,
            "currency": "SAR"
        },
        "can_be_cancelled": true
    }
}
```

### تتبع الحجز
```
GET /api/public/bookings/track/BK-2024-00001
Headers: X-Tenant-Slug: zain-sports
```

### إلغاء الحجز
```
POST /api/public/bookings/BK-2024-00001/cancel
Headers: X-Tenant-Slug: zain-sports

Body: { "reason": "تغيير الخطط" }
```

---

## 4. لوحة تحكم المالك (Owner)

> يحتاج: `Authorization: Bearer {token}` + `X-Tenant-Slug: {slug}`
> الدور المطلوب: `owner` أو `manager`

### لوحة الإحصائيات
```
GET /api/owner/dashboard

Response:
{
    "data": {
        "today": { "bookings": 12, "revenue": 2400 },
        "this_month": { "bookings": 145, "revenue": 28500, "discounts": 1200 },
        "last_month": { "bookings": 130, "revenue": 25000 },
        "growth": { "bookings_pct": 11.5, "revenue_pct": 14.0 },
        "pending_bookings": 3,
        "upcoming_bookings": 28,
        "cancellation_rate": 4.2,
        "top_fields": [...],
        "daily_revenue": [...],
        "sport_distribution": [...],
        "infrastructure": {
            "stadiums": 2,
            "fields": 8,
            "active_fields": 8
        }
    }
}
```

### إنشاء ملعب رئيسي
```
POST /api/owner/stadiums

Body:
{
    "name": "ملاعب زين - حي العليا",
    "city": "الرياض",
    "district": "حي العليا",
    "address": "شارع التحلية، الرياض",
    "latitude": 24.6877,
    "longitude": 46.6878,
    "phone": "+966114000002",
    "whatsapp": "+966501234567",
    "opens_at": "06:00",
    "closes_at": "24:00",
    "working_days": [0,1,2,3,4,5,6],
    "amenities": ["parking", "wifi"]
}
```

### إضافة ملعب فرعي
```
POST /api/owner/stadiums/{stadiumId}/fields

Body:
{
    "name": "ملعب بادل 2",
    "sport_type": "padel",
    "size": "standard",
    "capacity": 4,
    "surface_type": "artificial_grass",
    "price_per_hour": 120,
    "price_morning": 80,
    "price_evening": 140,
    "min_booking_duration": 60,
    "max_booking_duration": 120,
    "booking_slot_duration": 60,
    "has_lighting": true,
    "is_covered": true,
    "has_ac": true,
    "features": ["rackets", "balls"]
}
```

### إضافة قاعدة تسعير
```
POST /api/owner/fields/{id}/pricing-rules

Body:
{
    "name": "سعر نهاية الأسبوع",
    "type": "day_based",
    "days_of_week": [5, 6],
    "price": 200,
    "price_type": "fixed",
    "priority": 1
}

أنواع قواعد التسعير:
  time_based  → حسب الوقت (start_time / end_time)
  day_based   → حسب اليوم (days_of_week)
  date_range  → نطاق تواريخ (date_from / date_to)
  special     → خاص

أنواع السعر:
  fixed               → سعر ثابت
  percentage_increase → زيادة بنسبة %
  percentage_decrease → تخفيض بنسبة %
```

### حجب وقت (صيانة / إغلاق)
```
POST /api/owner/fields/{id}/block

Body (يوم كامل):
{
    "date": "2024-12-25",
    "is_full_day": true,
    "reason": "صيانة دورية"
}

Body (وقت محدد):
{
    "date": "2024-12-20",
    "start_time": "12:00",
    "end_time": "14:00",
    "reason": "تدريب منتخب"
}
```

### التقويم اليومي
```
GET /api/owner/bookings/calendar?date=2024-12-20&field_id=1

Response:
{
    "data": [
        {
            "booking_number": "BK-2024-00001",
            "field_name": "ملعب كرة قدم A",
            "customer_name": "محمد العتيبي",
            "start_time": "18:00",
            "end_time": "20:00",
            "status": "confirmed",
            "payment_status": "paid",
            "total_amount": 372.6
        }
    ]
}
```

### تأكيد / إلغاء الحجز
```
PATCH /api/owner/bookings/{id}/confirm
PATCH /api/owner/bookings/{id}/cancel      Body: { "reason": "..." }
PATCH /api/owner/bookings/{id}/complete
PATCH /api/owner/bookings/{id}/payment     Body: { "payment_method": "cash" }
```

---

## 5. Super Admin

> لا يحتاج X-Tenant-Slug
> الدور المطلوب: `super_admin`

### إنشاء حساب مالك جديد
```
POST /api/admin/tenants

Body:
{
    "tenant_name": "نادي الأهلي الرياضي",
    "tenant_email": "info@ahli.sa",
    "tenant_phone": "+966503000000",
    "plan": "professional",
    "owner_name": "عبدالله الأهلاوي",
    "owner_email": "owner@ahli.sa",
    "owner_password": "Pass@1234",
    "trial_days": 14
}
```

### إحصائيات المنصة
```
GET /api/admin/stats

Response:
{
    "data": {
        "tenants": {
            "total": 15,
            "active": 12,
            "trial": 2,
            "suspended": 1,
            "by_plan": { "basic": 8, "professional": 5, "enterprise": 2 }
        },
        "bookings": {
            "total": 4520,
            "this_month": 380,
            "revenue": 750000,
            "this_month_revenue": 62000
        },
        "infrastructure": {
            "stadiums": 45,
            "fields": 187,
            "users": 3200
        }
    }
}
```

---

## الباقات (Plans)

| الباقة | ملاعب رئيسية | ملاعب فرعية | السعر |
|--------|-------------|-------------|-------|
| Basic | 1 | 5 | مجاني/أساسي |
| Professional | 5 | 20 | متوسط |
| Enterprise | غير محدود | غير محدود | متقدم |

---

## رموز الأخطاء

| الكود | المعنى |
|-------|--------|
| `TENANT_NOT_FOUND` | الـ tenant غير موجود |
| `TENANT_SUSPENDED` | الحساب موقوف |
| `FORBIDDEN` | ليس لديك صلاحية |
| `TENANT_MISMATCH` | لا تنتمي لهذا الحساب |

---

## إعداد المشروع

```bash
# 1. إنشاء مشروع Laravel
composer create-project laravel/laravel stadium-saas
cd stadium-saas

# 2. تثبيت Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 3. إعداد .env
DB_CONNECTION=mysql
DB_DATABASE=stadium_saas
DB_USERNAME=root
DB_PASSWORD=

# 4. تشغيل الـ migrations
php artisan migrate

# 5. إضافة البيانات التجريبية
php artisan db:seed

# 6. تسجيل الـ Middleware في bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant' => \App\Http\Middleware\IdentifyTenant::class,
        'role'   => \App\Http\Middleware\CheckRole::class,
    ]);
})
```
