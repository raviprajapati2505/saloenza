<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            // Salon-wide overheads leave this null; branch costs scope the P&L filter.
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->string('category', 40);
            $table->string('title', 160);
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['saloon_id', 'incurred_on']);
            $table->index(['branch_id', 'incurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
