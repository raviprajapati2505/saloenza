<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('permissions', 'scope')) {
            return;
        }

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('scope', 20)->default('tenant');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('permissions', 'scope')) {
            return;
        }

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });
    }
};
