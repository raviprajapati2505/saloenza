<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saloon_subscription_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('saloon_subscription_id')->nullable()->constrained('saloon_subscriptions')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('from_subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('subscription_upgrade_order_id')->nullable()->constrained('subscription_upgrade_orders')->nullOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('status', 20);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::table('saloon_subscriptions', function (Blueprint $table): void {
            $table->timestamp('renewal_reminder_sent_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('saloon_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('renewal_reminder_sent_at');
        });

        Schema::dropIfExists('saloon_subscription_histories');
    }
};
