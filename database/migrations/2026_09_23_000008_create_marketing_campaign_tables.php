<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
            $table->string('name');
            $table->string('channel', 32); // email|sms
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 32)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->json('stats_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['saloon_id', 'channel']);
        });

        Schema::create('marketing_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('status', 32)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'customer_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaign_recipients');
        Schema::dropIfExists('marketing_campaigns');
    }
};
