<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->unsignedBigInteger('referrer_saloon_id')->nullable()->after('referral_code');
            $table->timestamp('owner_referred_at')->nullable()->after('referrer_saloon_id');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('saloons', function (Blueprint $table): void {
                $table->foreign('referrer_saloon_id')
                    ->references('id')
                    ->on('saloons')
                    ->nullOnDelete();
            });
        }

        Schema::create('salon_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('referred_saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code', 50)->nullable();
            $table->string('source', 20)->default('link');
            $table->string('status', 20)->default('in_progress');
            $table->json('metadata')->nullable();
            $table->timestamp('referred_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();

            $table->unique('referred_saloon_id');
            $table->index(['referrer_saloon_id', 'status']);
        });

        Schema::create('salon_referral_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('salon_referral_id')->nullable()->constrained('salon_referrals')->nullOnDelete();
            $table->foreignId('referred_saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('saloon_subscription_id')->nullable()->constrained('saloon_subscriptions')->nullOnDelete();
            $table->string('status', 20)->default('credited');
            $table->string('currency', 10)->default('INR');
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('credit_amount', 12, 2);
            $table->timestamp('credited_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('salon_referral_id');
            $table->index(['referrer_saloon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_referral_credits');
        Schema::dropIfExists('salon_referrals');

        Schema::table('saloons', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['referrer_saloon_id']);
            }
            $table->dropColumn(['referrer_saloon_id', 'owner_referred_at']);
        });
    }
};
