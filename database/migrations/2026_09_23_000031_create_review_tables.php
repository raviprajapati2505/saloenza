<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Google review URL is stored in salon_settings group "reviews" key "google_review_url"
        // (see config/tenant_settings.php). No saloons column required for P0.

        Schema::create('review_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('status', 24)->default('scheduled'); // scheduled|sent|clicked|skipped|suppressed
            $table->string('channel', 24)->default('manual');
            $table->string('google_link')->nullable();
            $table->boolean('google_link_eligible')->default(false);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('suppress_reason', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['appointment_id']);
            $table->index(['scheduled_for']);
        });

        Schema::create('service_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('review_request_id')->nullable()->constrained('review_requests')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->boolean('shared_publicly')->default(false);
            $table->timestamps();

            $table->index(['saloon_id', 'rating']);
            $table->index(['appointment_id']);
            $table->index(['staff_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ratings');
        Schema::dropIfExists('review_requests');
    }
};
