<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique();       // BK-2024-00001
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stadium_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = حجز بدون تسجيل

            // بيانات العميل (في حال الحجز بدون حساب)
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            // توقيت الحجز
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');

            // التسعير
            $table->decimal('price_per_hour', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_code')->nullable();
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency')->default('SAR');

            // الحالة
            $table->enum('status', [
                'pending',      // في انتظار التأكيد
                'confirmed',    // مؤكد
                'cancelled',    // ملغى
                'completed',    // مكتمل
                'no_show',      // لم يحضر
            ])->default('pending');

            // الدفع
            $table->enum('payment_status', [
                'unpaid', 'partially_paid', 'paid', 'refunded'
            ])->default('unpaid');
            $table->enum('payment_method', [
                'cash', 'card', 'bank_transfer', 'online', 'wallet'
            ])->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            // ملاحظات
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('cancelled_by')->nullable();      // user / owner / system
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // مصدر الحجز
            $table->enum('source', ['web', 'app', 'admin', 'walk_in', 'phone'])->default('web');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'booking_date']);
            $table->index(['field_id', 'booking_date', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['booking_date', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
