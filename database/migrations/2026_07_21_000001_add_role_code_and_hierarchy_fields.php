<?php

use App\Models\Role;
use App\Support\Role\RoleCodeGenerator;
use App\Support\Role\RoleCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('code', 120)->nullable()->after('name');
            $table->boolean('is_system')->default(false)->after('is_active');
            $table->string('scope', 20)->default('salon')->after('is_system');
            $table->foreignId('branch_id')->nullable()->after('saloon_id')->constrained('saloon_branches')->nullOnDelete();
            $table->unsignedTinyInteger('hierarchy_level')->default(50)->after('branch_id');
        });

        Role::query()->each(function (Role $role): void {
            $isLegacyOwner = $role->saloon_id === null
                && in_array($role->name, [RoleCodes::LEGACY_SALOON_OWNER, 'Owner'], true);

            if ($isLegacyOwner) {
                $role->forceFill([
                    'code' => RoleCodes::SALON_FRANCHISE_OWNER,
                    'name' => 'Salon Franchise Owner',
                    'is_system' => true,
                    'scope' => 'salon',
                    'hierarchy_level' => 10,
                ])->save();

                return;
            }

            $role->forceFill([
                'code' => RoleCodeGenerator::forExistingRole($role),
                'scope' => $role->saloon_id === null ? 'salon' : 'salon',
            ])->save();
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['code', 'is_system', 'scope', 'hierarchy_level']);
        });
    }
};
