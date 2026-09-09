<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_interval', 20)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->json('modules');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
