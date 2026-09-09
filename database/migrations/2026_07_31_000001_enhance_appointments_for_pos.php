<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('type', 20)->default('appointment')->after('status');
            $table->index(['saloon_id', 'type']);
        });

        Schema::create('appointment_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['staff_id', 'starts_at', 'ends_at']);
            $table->index(['appointment_id', 'sort_order']);
        });

        $this->backfillAppointmentServices();
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_services');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['saloon_id', 'type']);
            $table->dropColumn('type');
        });
    }

    private function backfillAppointmentServices(): void
    {
        $appointments = DB::table('appointments')
            ->whereNotNull('service_id')
            ->orderBy('id')
            ->get(['id', 'service_id', 'product_id', 'staff_id', 'price', 'starts_at', 'ends_at']);

        $now = now();

        foreach ($appointments as $appointment) {
            $startsAt = $appointment->starts_at;
            $endsAt = $appointment->ends_at;
            $duration = 60;

            if ($startsAt && $endsAt) {
                $duration = max(1, (int) round((strtotime((string) $endsAt) - strtotime((string) $startsAt)) / 60));
            }

            DB::table('appointment_services')->insert([
                'appointment_id' => $appointment->id,
                'service_id' => $appointment->service_id,
                'product_id' => $appointment->product_id,
                'staff_id' => $appointment->staff_id,
                'price' => $appointment->price ?? 0,
                'duration_minutes' => $duration,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
