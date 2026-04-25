<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stadium_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // ملعب A، ملعب 1

            // النوع
            $table->enum('sport_type', [
                'football', 'basketball', 'volleyball',
                'tennis', 'padel', 'squash', 'badminton',
                'cricket', 'futsal', 'multi_sport'
            ])->default('football');

            // الحجم
            $table->enum('size', [
                '5x5', '7x7', '8x8', '9x9', '11x11',
                'half_court', 'full_court', 'standard', 'custom'
            ])->default('5x5');
            $table->string('dimensions')->nullable();         // مثلاً: 40m x 20m
            $table->integer('capacity')->default(10);         // عدد اللاعبين

            // نوع الأرضية
            $table->enum('surface_type', [
                'natural_grass', 'artificial_grass',
                'concrete', 'wooden', 'rubber', 'clay', 'sand'
            ])->default('artificial_grass');

            // التسعير
            $table->decimal('price_per_hour', 10, 2);
            $table->decimal('price_weekday', 10, 2)->nullable();   // سعر أيام الأسبوع
            $table->decimal('price_weekend', 10, 2)->nullable();   // سعر عطلة نهاية الأسبوع
            $table->decimal('price_morning', 10, 2)->nullable();   // سعر الصباح
            $table->decimal('price_evening', 10, 2)->nullable();   // سعر المساء
            $table->string('currency')->default('SAR');
            $table->integer('min_booking_duration')->default(60);  // بالدقائق
            $table->integer('max_booking_duration')->default(180); // بالدقائق
            $table->integer('booking_slot_duration')->default(60); // مدة كل slot

            // الإضاءة والمرافق
            $table->boolean('has_lighting')->default(true);
            $table->boolean('is_covered')->default(false);     // مسقوف
            $table->boolean('has_ac')->default(false);
            $table->json('features')->nullable();              // [balls, bibs, referee...]

            $table->boolean('is_active')->default(true);
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stadium_id', 'sport_type', 'is_active']);
            $table->index(['tenant_id', 'sport_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
