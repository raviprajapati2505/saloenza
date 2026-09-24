<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('currency', 10)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('commission_on_no_show')->default(false);
            $table->string('commission_when_payment_unpaid', 32)->default('on_complete');
            $table->timestamps();

            $table->index(['saloon_id', 'is_active']);
            $table->index(['saloon_id', 'is_default']);
        });

        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_id')->constrained('commission_schemes')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->string('name', 120);
            $table->enum('applies_to', ['service', 'product', 'all'])->default('all');
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->enum('calc_type', [
                'percent_of_revenue',
                'percent_of_net',
                'fixed_per_line',
                'fixed_per_minute',
                'tiered_percent',
            ])->default('percent_of_revenue');
            $table->decimal('rate_value', 12, 4)->default(0);
            $table->json('tier_json')->nullable();
            $table->boolean('include_discounts')->default(true);
            $table->decimal('min_line_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scheme_id', 'priority']);
            $table->index(['scheme_id', 'is_active']);
        });

        Schema::create('staff_commission_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('scheme_id')->constrained('commission_schemes')->cascadeOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->decimal('override_percent', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'saloon_id']);
            $table->index(['scheme_id']);
            $table->index(['branch_id']);
        });

        Schema::create('commission_payout_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('open');
            $table->decimal('total_commission', 12, 2)->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['saloon_id', 'period_start', 'period_end']);
        });

        Schema::create('commission_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedBigInteger('appointment_service_id')->nullable();
            $table->unsignedBigInteger('appointment_product_id')->nullable();
            $table->foreignId('rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->decimal('basis_amount', 12, 2)->default(0);
            $table->string('calc_type', 40)->nullable();
            $table->decimal('rate_applied', 12, 4)->nullable();
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('status', 24)->default('pending');
            $table->string('exclusion_reason', 80)->nullable();
            $table->date('earned_on')->nullable();
            $table->foreignId('payout_period_id')->nullable()->constrained('commission_payout_periods')->nullOnDelete();
            $table->timestamps();

            $table->index(['saloon_id', 'user_id', 'earned_on']);
            $table->index(['payout_period_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_line_items');
        Schema::dropIfExists('commission_payout_periods');
        Schema::dropIfExists('staff_commission_assignments');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('commission_schemes');
    }
};
