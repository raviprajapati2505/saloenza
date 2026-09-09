<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saloon_subscriptions', function (Blueprint $table): void {
            $table->json('renewal_reminder_days_sent')->nullable()->after('renewal_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('saloon_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('renewal_reminder_days_sent');
        });
    }
};
