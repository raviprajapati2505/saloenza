<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_system_admin')->default(false)->after('photo');
            $table->foreignId('role_id')->nullable()->after('is_system_admin')->constrained('roles')->nullOnDelete();
        });

        $ownerRole = Role::query()->create([
            'name' => 'Owner',
            'is_active' => true,
            'saloon_id' => null,
        ]);

        User::query()
            ->where('role', 'owner')
            ->update([
                'role_id' => $ownerRole->id,
            ]);

        User::query()
            ->where('role', 'super_admin')
            ->update([
                'is_system_admin' => true,
                'role_id' => null,
            ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 50)->default('owner')->after('photo');
        });

        User::query()
            ->where('is_system_admin', true)
            ->update(['role' => 'super_admin']);

        User::query()
            ->whereNotNull('role_id')
            ->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_system_admin');
        });
    }
};
