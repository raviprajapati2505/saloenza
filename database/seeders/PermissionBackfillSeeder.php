<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Adds permissions introduced by new features to an existing database.
 *
 * `ApplicationPermissionSeeder` truncates the permission table and re-syncs every
 * system role, which is fine for a fresh install but drops whatever a salon has
 * granted to its own custom roles. This seeder is purely additive: it creates the
 * permissions that are missing and grants them only to the system roles whose
 * definition already lists them.
 *
 * Permissions that already exist are left alone, including their role assignments,
 * so an admin who deliberately revoked one will not have it handed back.
 */
class PermissionBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPermissionMetadata();

        $createdCodes = $this->createMissingPermissions();

        if ($createdCodes === []) {
            $this->command?->info('Permissions are already up to date.');

            return;
        }

        $this->command?->info('Added permissions: '.implode(', ', $createdCodes));

        $grants = $this->grantToSystemRoles($createdCodes);

        foreach ($grants as $roleName => $codes) {
            $this->command?->info(sprintf('  %s → %s', $roleName, implode(', ', $codes)));
        }
    }

    /**
     * @return list<string>
     */
    private function createMissingPermissions(): array
    {
        $existing = Permission::query()->pluck('code')->all();
        $created = [];

        foreach (ApplicationPermissionSeeder::definitions() as $definition) {
            if (in_array($definition['code'], $existing, true)) {
                continue;
            }

            Permission::query()->create($definition);
            $created[] = $definition['code'];
        }

        return $created;
    }

    /**
     * @param  list<string>  $createdCodes
     * @return array<string, list<string>>
     */
    private function grantToSystemRoles(array $createdCodes): array
    {
        $granted = [];

        foreach (RoleSeeder::systemRoleDefinitions() as $definition) {
            $codes = array_values(array_intersect($definition['permissions'], $createdCodes));

            if ($codes === []) {
                continue;
            }

            $role = Role::query()->where('code', $definition['code'])->first();

            if ($role === null) {
                continue;
            }

            $permissionIds = Permission::query()->whereIn('code', $codes)->pluck('id')->all();
            $role->permissions()->syncWithoutDetaching($permissionIds);

            $granted[$definition['name']] = $codes;
        }

        return $granted;
    }

    private function syncPermissionMetadata(): void
    {
        foreach (ApplicationPermissionSeeder::definitions() as $definition) {
            Permission::query()
                ->where('code', $definition['code'])
                ->update([
                    'name' => $definition['name'],
                    'module' => $definition['module'],
                    'scope' => $definition['scope'],
                ]);
        }
    }
}
