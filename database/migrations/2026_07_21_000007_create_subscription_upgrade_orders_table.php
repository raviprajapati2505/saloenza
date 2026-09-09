<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_upgrade_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('to_subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('channel', 30)->default('manual');
            $table->string('status', 30)->default('pending');
            $table->string('payment_type', 50)->nullable();
            $table->string('transaction_id', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('gateway_order_id', 120)->nullable();
            $table->string('gateway_payment_id', 120)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_upgrade_orders');
    }
};
