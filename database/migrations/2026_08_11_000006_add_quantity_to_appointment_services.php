<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->default(1)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_services', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
