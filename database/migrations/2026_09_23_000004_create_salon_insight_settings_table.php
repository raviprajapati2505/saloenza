<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-salon insight thresholds. P0 SalonInsightsService computes on the fly
 * and falls back to defaults when no row exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_insight_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->unsignedSmallInteger('lapse_days_threshold')->default(45);
            $table->decimal('high_value_spend_threshold', 12, 2)->nullable();
            $table->unsignedSmallInteger('imbalance_variance_pct')->default(25);
            $table->unsignedSmallInteger('lookback_days')->default(90);
            $table->decimal('recovery_rate_assumption', 5, 4)->default(0.15);
            $table->json('enabled_insight_types')->nullable();
            $table->timestamps();

            $table->unique('saloon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_insight_settings');
    }
};
