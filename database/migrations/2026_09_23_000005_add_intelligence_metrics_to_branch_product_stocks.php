<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_product_stocks', function (Blueprint $table): void {
            $table->decimal('avg_daily_sales_7d', 12, 4)->nullable()->after('max_stock');
            $table->decimal('avg_daily_sales_30d', 12, 4)->nullable()->after('avg_daily_sales_7d');
            $table->decimal('avg_daily_sales_90d', 12, 4)->nullable()->after('avg_daily_sales_30d');
            $table->decimal('days_of_cover', 10, 2)->nullable()->after('avg_daily_sales_90d');
            $table->decimal('days_to_stockout', 10, 2)->nullable()->after('days_of_cover');
            $table->string('velocity_class', 32)->nullable()->after('days_to_stockout');
            $table->unsignedInteger('suggested_reorder_qty')->nullable()->after('velocity_class');
            $table->timestamp('metrics_updated_at')->nullable()->after('suggested_reorder_qty');

            $table->index(['branch_id', 'velocity_class'], 'bps_branch_velocity_idx');
            $table->index(['branch_id', 'days_to_stockout'], 'bps_branch_stockout_idx');
            $table->index('metrics_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('branch_product_stocks', function (Blueprint $table): void {
            $table->dropIndex('bps_branch_velocity_idx');
            $table->dropIndex('bps_branch_stockout_idx');
            $table->dropIndex(['metrics_updated_at']);
            $table->dropColumn([
                'avg_daily_sales_7d',
                'avg_daily_sales_30d',
                'avg_daily_sales_90d',
                'days_of_cover',
                'days_to_stockout',
                'velocity_class',
                'suggested_reorder_qty',
                'metrics_updated_at',
            ]);
        });
    }
};
