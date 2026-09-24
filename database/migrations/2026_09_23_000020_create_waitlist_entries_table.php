<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('preferred_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('earliest_at')->nullable();
            $table->timestamp('latest_at')->nullable();
            $table->json('preferred_days')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status', 30)->default('waiting');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['saloon_id', 'branch_id', 'status']);
            $table->index(['saloon_id', 'service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
