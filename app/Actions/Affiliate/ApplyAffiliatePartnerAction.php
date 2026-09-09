<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliatePartner;
use App\Models\Role;
use App\Models\User;
use App\Support\Affiliate\AffiliateCodeGenerator;
use App\Support\Role\RoleCodes;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyAffiliatePartnerAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): AffiliatePartner
    {
        $role = Role::findByCode(RoleCodes::AFFILIATE_PARTNER);
        if ($role === null) {
            throw new RuntimeException('Affiliate partner role is not configured. Run RoleSeeder.');
        }

        return DB::transaction(function () use ($payload, $role): AffiliatePartner {
            $name = trim((string) $payload['name']);
            $displayName = trim((string) ($payload['display_name'] ?? $name));

            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower(trim((string) $payload['email'])),
                'phone' => trim((string) $payload['phone']),
                'password' => (string) $payload['password'],
                'role_id' => $role->id,
                'is_active' => true,
                'onboarding_completed_at' => now(),
                'email_verified_at' => now(),
            ]);

            return AffiliatePartner::query()->create([
                'user_id' => $user->id,
                'code' => AffiliateCodeGenerator::generate($displayName),
                'display_name' => $displayName,
                'status' => AffiliatePartner::STATUS_PENDING,
                'onboarding_commission_rate' => $payload['onboarding_commission_rate'] ?? 12,
                'renewal_commission_rate' => $payload['renewal_commission_rate'] ?? 5,
                'commission_lock_days' => $payload['commission_lock_days'] ?? 30,
                'payout_method' => $payload['payout_method'] ?? null,
                'payout_details' => $payload['payout_details'] ?? null,
                'notes' => isset($payload['notes']) ? trim((string) $payload['notes']) : null,
                'joined_at' => now(),
                'activated_at' => null,
            ]);
        });
    }
}
