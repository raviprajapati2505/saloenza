<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saloon_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saloon_subscriptions');
    }
};
