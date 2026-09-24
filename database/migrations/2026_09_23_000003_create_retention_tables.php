<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('at_risk_after_days')->default(40);
            $table->unsignedSmallInteger('lapsed_after_days')->default(60);
            $table->unsignedSmallInteger('lost_after_days')->default(120);
            $table->unsignedSmallInteger('min_visits_required')->default(1);
            $table->json('exclude_tag_ids')->nullable();
            $table->json('channels_allowed')->nullable(); // email|sms|manual
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['saloon_id', 'is_active']);
        });

        Schema::create('retention_cohorts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('retention_policies')->nullOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('draft'); // draft|ready|sending|completed|cancelled
            $table->json('segment_filter_json')->nullable();
            $table->string('template_channel', 16)->default('manual'); // email|sms|manual
            $table->string('template_code')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('stats_json')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
        });

        Schema::create('retention_cohort_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cohort_id')->constrained('retention_cohorts')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('lapse_status_at_add', 16)->nullable();
            $table->decimal('priority_score', 12, 2)->default(0);
            $table->string('message_status', 32)->default('pending'); // pending|sent|skipped|failed|opted_out|manual
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('response_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('skip_reason')->nullable();
            $table->timestamps();

            $table->unique(['cohort_id', 'customer_id']);
            $table->index(['cohort_id', 'message_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_cohort_members');
        Schema::dropIfExists('retention_cohorts');
        Schema::dropIfExists('retention_policies');
    }
};
