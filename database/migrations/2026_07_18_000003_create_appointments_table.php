<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 30)->default('scheduled');
            $table->decimal('price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'starts_at']);
            $table->index(['saloon_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
