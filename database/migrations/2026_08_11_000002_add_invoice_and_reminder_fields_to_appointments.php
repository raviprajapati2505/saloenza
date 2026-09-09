<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('invoice_number', 64)->nullable()->after('grand_total');
            $table->timestamp('reminder_sent_at')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['invoice_number', 'reminder_sent_at']);
        });
    }
};
