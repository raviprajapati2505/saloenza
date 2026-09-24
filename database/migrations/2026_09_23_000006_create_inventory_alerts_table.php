<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('saloon_branches')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('type', 64);
            $table->string('severity', 32)->default('medium');
            $table->string('message');
            $table->string('status', 32)->default('open');
            $table->json('metrics_json')->nullable();
            $table->timestamps();

            $table->index(['saloon_id', 'status', 'type']);
            $table->index(['branch_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_alerts');
    }
};
