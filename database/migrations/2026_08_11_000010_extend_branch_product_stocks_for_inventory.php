<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_product_stocks', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained('suppliers')->nullOnDelete();
            $table->decimal('cost_price', 10, 2)->nullable()->after('reorder_level');
            $table->decimal('selling_price', 10, 2)->nullable()->after('cost_price');
            $table->unsignedInteger('max_stock')->default(100)->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('branch_product_stocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['cost_price', 'selling_price', 'max_stock']);
        });
    }
};
