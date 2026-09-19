<?php

namespace App\Http\Resources\Api\V1\Shared;

use App\Http\Resources\Api\V1\Affiliate\AffiliatePartnerResource;
use App\Http\Resources\Api\V1\Role\RoleResource;
use App\Http\Resources\Api\V1\Shared\PermissionsCollection;
use App\Http\Resources\Api\V1\Shared\TenantResource;
use App\Http\Resources\Api\V1\Shared\UserResource;
use App\Models\User;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionModules;
use App\Support\UserPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedContextResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource['user'];
        $user->loadMissing(['branch', 'affiliatePartner']);
        $saloon = $user->saloon;
        $subscriptionSnapshot = $saloon
            ? app(SubscriptionEntitlements::class)->snapshot($saloon)
            : null;
        $workspace = $user->affiliatePartner !== null || $user->role?->scope === 'affiliate'
            ? 'affiliate'
            : ($user->role?->scope === 'platform' || $user->is_system_admin ? 'platform' : 'tenant');

        return [
            'user' => new UserResource($user),
            'tenant' => $saloon ? new TenantResource($saloon) : null,
            'affiliate_partner' => $user->affiliatePartner ? new AffiliatePartnerResource($user->affiliatePartner) : null,
            'is_system_admin' => (bool) $user->is_system_admin,
            'should_onboard' => $user->shouldOnboard(),
            'workspace' => $workspace,
            'role' => $user->role ? new RoleResource($user->role) : null,
            'permissions' => new PermissionsCollection(UserPermissions::codesFor($user)),
            'subscription_modules' => $subscriptionSnapshot['modules'] ?? [],
            'subscription_limits' => $subscriptionSnapshot['limits'] ?? null,
            'subscription_module_catalog' => SubscriptionModules::catalogPayload(),
            'platform_branding' => app(\App\Services\Platform\PlatformSettingsService::class)->brandingPayload(),
        ];
    }
}
