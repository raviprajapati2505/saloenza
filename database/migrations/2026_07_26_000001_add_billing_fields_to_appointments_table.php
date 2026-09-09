<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('service_id')
                ->constrained('products')
                ->nullOnDelete();

            $table->decimal('discount', 10, 2)->default(0)->after('price');
            $table->decimal('grand_total', 10, 2)->nullable()->after('discount');

            $table->foreignId('created_by')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['discount', 'grand_total']);
        });
    }
};
