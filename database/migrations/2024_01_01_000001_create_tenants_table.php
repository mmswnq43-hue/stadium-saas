<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // اسم الشركة / المالك
            $table->string('slug')->unique();                // zain-sports
            $table->string('domain')->nullable()->unique();  // zain.stadiums.com
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('logo')->nullable();
            $table->enum('plan', ['basic', 'professional', 'enterprise'])->default('basic');
            $table->enum('status', ['active', 'suspended', 'trial'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->json('settings')->nullable();            // إعدادات خاصة بكل tenant
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
