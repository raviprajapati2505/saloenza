<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('users', 'joined_at')) {
                $table->date('joined_at')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('users', 'weekly_schedule')) {
                $table->json('weekly_schedule')->nullable()->after('joined_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['notes', 'joined_at', 'weekly_schedule'],
                fn (string $column): bool => Schema::hasColumn('users', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
