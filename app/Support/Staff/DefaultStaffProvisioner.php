<?php

namespace App\Support\Staff;

use App\Models\Role;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Support\Str;

class DefaultStaffProvisioner
{
    /**
     * Ensure the salon has at least one staff-role user on the given branch.
     */
    public function ensureDefaultStaff(Saloon $saloon, SaloonBranch $branch, ?string $password = null): ?User
    {
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);
        if ($staffRole === null) {
            return null;
        }

        $existing = User::query()
            ->where('saloon_id', $saloon->id)
            ->where('role_id', $staffRole->id)
            ->where('is_active', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $slug = Str::lower(Str::slug(Str::limit((string) $saloon->name, 24, ''), '') ?: 'salon');
        $token = Str::lower(Str::random(5));

        $phone = null;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = fake()->numerify('+919#########');
            if (! User::query()->where('phone', $candidate)->exists()) {
                $phone = $candidate;
                break;
            }
        }

        return User::query()->create([
            'name' => 'Default Staff',
            'firstname' => 'Default',
            'lastname' => 'Staff',
            'email' => "staff.{$slug}.{$token}@salon.local",
            'phone' => $phone ?? ('+919'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT)),
            'password' => $password ?: Str::password(12),
            'role_id' => $staffRole->id,
            'saloon_id' => $saloon->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
    }
}
