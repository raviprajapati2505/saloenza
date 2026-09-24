<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('import_key', 120)->nullable()->after('booking_source');
            $table->unique(['saloon_id', 'import_key']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique(['saloon_id', 'import_key']);
            $table->dropColumn('import_key');
        });
    }
};
