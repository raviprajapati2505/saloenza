<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saloon_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saloon_id')->constrained('saloons')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['saloon_id', 'customer_id']);
            $table->index(['customer_id', 'saloon_id']);
        });

        if (Schema::hasColumn('customers', 'saloon_id')) {
            $now = now();

            DB::table('customers')
                ->select(['id', 'saloon_id'])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($now): void {
                    $inserts = [];

                    foreach ($rows as $row) {
                        if ($row->saloon_id === null) {
                            continue;
                        }

                        $inserts[] = [
                            'saloon_id' => $row->saloon_id,
                            'customer_id' => $row->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($inserts !== []) {
                        DB::table('saloon_customers')->insertOrIgnore($inserts);
                    }
                });

            Schema::table('customers', function (Blueprint $table): void {
                $table->dropIndex(['saloon_id', 'is_active']);
                $table->dropConstrainedForeignId('saloon_id');
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'saloon_id')) {
                $table->foreignId('saloon_id')->nullable()->after('id')->constrained('saloons')->nullOnDelete();
            }
        });

        $firstLinks = DB::table('saloon_customers')
            ->select('customer_id', DB::raw('MIN(saloon_id) as saloon_id'))
            ->groupBy('customer_id')
            ->get();

        foreach ($firstLinks as $link) {
            DB::table('customers')
                ->where('id', $link->customer_id)
                ->update(['saloon_id' => $link->saloon_id]);
        }

        Schema::dropIfExists('saloon_customers');

        Schema::table('customers', function (Blueprint $table): void {
            $table->index(['saloon_id', 'is_active']);
        });
    }
};
