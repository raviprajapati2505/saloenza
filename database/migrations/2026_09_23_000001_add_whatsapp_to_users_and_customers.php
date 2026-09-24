<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'whatsapp')) {
                $table->string('whatsapp', 20)->nullable()->after('phone');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'whatsapp')) {
                $table->dropColumn('whatsapp');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'whatsapp')) {
                $table->dropColumn('whatsapp');
            }
        });
    }
};
