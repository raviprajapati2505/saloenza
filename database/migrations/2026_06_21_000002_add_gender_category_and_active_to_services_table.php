<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('gender', 20)->nullable()->after('duration_minutes');
            // $table->foreignId('category_id')->nullable()->after('gender')->constrained('categories')->nullOnDelete();
            // $table->boolean('is_active')->default(true)->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // $table->dropConstrainedForeignId('category_id');
            // $table->dropColumn(['gender', 'is_active']);
        });
    }
};
