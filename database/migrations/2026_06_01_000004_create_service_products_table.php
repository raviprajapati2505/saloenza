<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('default_price', 10, 2);
            $table->timestamps();

            $table->unique(['service_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_products');
    }
};
