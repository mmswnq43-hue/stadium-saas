<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stadiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // الموقع الجغرافي
            $table->string('country')->default('SA');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('google_maps_url')->nullable();

            // معلومات الاتصال
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();

            // الوقت
            $table->time('opens_at')->default('06:00:00');
            $table->time('closes_at')->default('24:00:00');
            $table->json('working_days')->nullable(); // [0,1,2,3,4,5,6] أيام العمل

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('amenities')->nullable(); // [wifi, parking, cafeteria, showers...]
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['city', 'is_active']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stadiums');
    }
};
