<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('categories')
                    ->nullOnDelete();
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('categories')
                    ->nullOnDelete();
            }
        });

        $defaultCategoryId = Category::query()->where('is_active', true)->orderBy('id')->value('id');

        if ($defaultCategoryId !== null) {
            DB::table('products')->whereNull('category_id')->update(['category_id' => $defaultCategoryId]);
            DB::table('services')->whereNull('category_id')->update(['category_id' => $defaultCategoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });
    }
};
