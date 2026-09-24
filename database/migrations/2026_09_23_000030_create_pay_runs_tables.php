<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('draft'); // draft|approved|paid
            $table->decimal('total_basic', 12, 2)->default(0);
            $table->decimal('total_commission', 12, 2)->default(0);
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['saloon_id', 'period_start', 'period_end']);
        });

        Schema::create('pay_run_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pay_run_id')->constrained('pay_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->json('earnings_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pay_run_id', 'user_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_run_lines');
        Schema::dropIfExists('pay_runs');
    }
};
