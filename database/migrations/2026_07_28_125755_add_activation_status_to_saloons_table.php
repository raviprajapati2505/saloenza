<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saloons', function (Blueprint $table) {
            $table->string('activation_status', 40)
                ->default('active')
                ->after('is_active')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('saloons', function (Blueprint $table) {
            $table->dropColumn('activation_status');
        });
    }
};
