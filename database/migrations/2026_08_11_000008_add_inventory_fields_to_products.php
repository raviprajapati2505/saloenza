<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('sku', 60)->nullable()->after('name');
            $table->string('brand', 80)->nullable()->after('sku');
            $table->string('unit', 30)->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['sku', 'brand', 'unit']);
        });
    }
};
