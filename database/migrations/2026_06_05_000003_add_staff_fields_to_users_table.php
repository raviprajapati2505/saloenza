<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('firstname', 120)->nullable()->after('name');
            $table->string('lastname', 120)->nullable()->after('firstname');
            $table->foreignId('branch_id')->nullable()->after('saloon_id')->constrained('saloon_branches')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['firstname', 'lastname', 'is_active']);
        });
    }
};
