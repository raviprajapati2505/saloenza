<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->string('payment_type', 50)->nullable()->after('working_hours');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_type');
            $table->string('transaction_id', 120)->nullable()->after('payment_amount');
            $table->boolean('is_active')->default(true)->after('transaction_id');
            $table->string('referral_code', 50)->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_type',
                'payment_amount',
                'transaction_id',
                'is_active',
                'referral_code',
            ]);
        });
    }
};
