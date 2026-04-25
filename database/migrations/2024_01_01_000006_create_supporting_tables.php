<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قواعد التسعير الديناميكية
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->string('name');                              // مثال: سعر المساء في رمضان
            $table->enum('type', ['time_based', 'day_based', 'date_range', 'special']);
            $table->json('days_of_week')->nullable();             // [0=Sun, 6=Sat]
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('price_type', ['fixed', 'percentage_increase', 'percentage_decrease'])->default('fixed');
            $table->integer('priority')->default(0);             // أعلى priority يطغى
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // الأوقات المحجوبة (صيانة، إغلاق مؤقت)
        Schema::create('blocked_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('reason')->nullable();
            $table->boolean('is_full_day')->default(false);
            $table->timestamps();

            $table->index(['field_id', 'date']);
        });

        // كوبونات الخصم
        Schema::create('discount_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->decimal('min_booking_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_user')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // الإشعارات
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                              // booking_confirmed, reminder...
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->enum('channel', ['database', 'sms', 'email', 'whatsapp'])->default('database');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('discount_coupons');
        Schema::dropIfExists('blocked_slots');
        Schema::dropIfExists('pricing_rules');
    }
};
