<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_service_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['saloon_id', 'service_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_service_products');
    }
};
