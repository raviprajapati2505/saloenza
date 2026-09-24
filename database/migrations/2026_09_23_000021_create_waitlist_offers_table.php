<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('waitlist_entry_id')->constrained('waitlist_entries')->cascadeOnDelete();
            $table->foreignId('source_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamp('slot_starts_at');
            $table->timestamp('slot_ends_at')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('channel', 30)->default('staff');
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('response_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamps();

            $table->index(['saloon_id', 'status']);
            $table->index(['waitlist_entry_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_offers');
    }
};
