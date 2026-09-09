<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('saloon_branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('reorder_level')->default(5);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index(['branch_id', 'quantity_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_stocks');
    }
};
