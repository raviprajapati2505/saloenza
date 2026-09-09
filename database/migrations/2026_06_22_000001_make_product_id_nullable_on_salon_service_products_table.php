<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_service_products', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE salon_service_products ALTER COLUMN product_id DROP NOT NULL');
        } else {
            Schema::table('salon_service_products', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }

        Schema::table('salon_service_products', function (Blueprint $table): void {
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salon_service_products', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE salon_service_products ALTER COLUMN product_id SET NOT NULL');
        } else {
            Schema::table('salon_service_products', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            });
        }

        Schema::table('salon_service_products', function (Blueprint $table): void {
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }
};
