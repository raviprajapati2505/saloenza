<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_value_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->decimal('lifetime_spend', 12, 2)->default(0);
            $table->unsignedInteger('visit_count')->default(0);
            $table->decimal('avg_ticket', 12, 2)->default(0);
            $table->timestamp('first_visit_at')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->decimal('avg_days_between_visits', 8, 2)->nullable();
            $table->decimal('expected_annual_value', 12, 2)->nullable();
            $table->unsignedTinyInteger('clv_score')->default(0);
            $table->string('clv_tier', 16)->default('bronze'); // platinum|gold|silver|bronze
            $table->unsignedTinyInteger('churn_risk_score')->nullable();
            $table->string('lapse_status', 16)->default('active'); // active|at_risk|lapsed|lost
            $table->timestamp('lapse_status_updated_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'saloon_id']);
            $table->index(['saloon_id', 'clv_tier']);
            $table->index(['saloon_id', 'lapse_status']);
            $table->index(['saloon_id', 'clv_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_value_stats');
    }
};
