<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('saloon_branches')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            // Snapshot of branch cost at sale time so margin reports stay accurate after repricing.
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('line_total', 10, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['appointment_id', 'sort_order']);
            $table->index(['product_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->decimal('services_total', 10, 2)->default(0)->after('price');
            $table->decimal('products_total', 10, 2)->default(0)->after('services_total');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['services_total', 'products_total']);
        });

        Schema::dropIfExists('appointment_products');
    }
};
