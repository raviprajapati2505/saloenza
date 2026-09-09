<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('unpaid')->after('grand_total');
            $table->string('payment_method', 30)->nullable()->after('payment_status');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('amount_paid');

            $table->index(['saloon_id', 'payment_status']);
            $table->index(['saloon_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['saloon_id', 'payment_status']);
            $table->dropIndex(['saloon_id', 'paid_at']);
            $table->dropColumn(['payment_status', 'payment_method', 'amount_paid', 'paid_at']);
        });
    }
};
