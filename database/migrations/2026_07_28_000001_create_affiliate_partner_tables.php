<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('display_name', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('onboarding_commission_rate', 5, 2)->default(12);
            $table->decimal('renewal_commission_rate', 5, 2)->default(5);
            $table->unsignedInteger('commission_lock_days')->default(30);
            $table->string('payout_method', 40)->nullable();
            $table->json('payout_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('saloons', function (Blueprint $table): void {
            $table->unsignedBigInteger('affiliate_partner_id')->nullable();
            $table->string('affiliate_referral_code', 40)->nullable();
            $table->timestamp('affiliate_attributed_at')->nullable();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('saloons', function (Blueprint $table): void {
                $table->foreign('affiliate_partner_id')
                    ->references('id')
                    ->on('affiliate_partners')
                    ->nullOnDelete();
            });
        }

        Schema::create('affiliate_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_partner_id')->constrained('affiliate_partners')->cascadeOnDelete();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code', 40)->nullable();
            $table->string('source', 20)->default('link');
            $table->json('metadata')->nullable();
            $table->timestamp('referred_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->unique('saloon_id');
            $table->index(['affiliate_partner_id', 'source']);
        });

        Schema::create('affiliate_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_partner_id')->constrained('affiliate_partners')->cascadeOnDelete();
            $table->foreignId('affiliate_referral_id')->nullable()->constrained('affiliate_referrals')->nullOnDelete();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('subscription_upgrade_order_id')->nullable()->constrained('subscription_upgrade_orders')->nullOnDelete();
            $table->foreignId('saloon_subscription_id')->nullable()->constrained('saloon_subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('affiliate_withdrawal_request_id')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->default('locked');
            $table->string('currency', 10)->default('INR');
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['affiliate_partner_id', 'status']);
            $table->index(['saloon_id', 'type']);
        });

        Schema::create('affiliate_withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_partner_id')->constrained('affiliate_partners')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('status', 20)->default('pending');
            $table->string('payout_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_partner_id', 'status']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('affiliate_commissions', function (Blueprint $table): void {
                $table->foreign('affiliate_withdrawal_request_id')
                    ->references('id')
                    ->on('affiliate_withdrawal_requests')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('affiliate_commissions', function (Blueprint $table): void {
                $table->dropForeign(['affiliate_withdrawal_request_id']);
            });
        }

        Schema::dropIfExists('affiliate_withdrawal_requests');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('saloons', function (Blueprint $table): void {
                $table->dropForeign(['affiliate_partner_id']);
            });
        }

        Schema::table('saloons', function (Blueprint $table): void {
            $table->dropColumn(['affiliate_partner_id', 'affiliate_referral_code', 'affiliate_attributed_at']);
        });

        Schema::dropIfExists('affiliate_partners');
    }
};
