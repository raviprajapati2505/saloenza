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
            $table->foreignId('branch_id')
                ->nullable()
                ->after('saloon_id')
                ->constrained('saloon_branches')
                ->nullOnDelete();
        });

        DB::table('salon_service_products')
            ->select('salon_service_products.id', 'salon_service_products.saloon_id')
            ->orderBy('salon_service_products.id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $branchId = DB::table('saloon_branches')
                        ->where('saloon_id', $row->saloon_id)
                        ->orderBy('id')
                        ->value('id');

                    if ($branchId === null) {
                        continue;
                    }

                    DB::table('salon_service_products')
                        ->where('id', $row->id)
                        ->update(['branch_id' => $branchId]);
                }
            });

        Schema::table('salon_service_products', function (Blueprint $table): void {
            $table->dropUnique('salon_service_products_saloon_id_service_id_product_id_unique');
            $table->unique(
                ['saloon_id', 'branch_id', 'service_id', 'product_id'],
                'salon_service_products_saloon_branch_service_product_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('salon_service_products', function (Blueprint $table): void {
            $table->dropUnique('salon_service_products_saloon_branch_service_product_unique');
            $table->unique(['saloon_id', 'service_id', 'product_id']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
