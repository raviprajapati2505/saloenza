<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->string('domain', 63)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('saloons', function (Blueprint $table): void {
            $table->dropUnique(['domain']);
            $table->dropColumn('domain');
        });
    }
};
