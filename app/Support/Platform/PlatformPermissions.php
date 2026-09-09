<?php

namespace App\Support\Platform;

final class PlatformPermissions
{
    public const ONBOARDING_MANAGE = 'platform.salon_onboarding.view';

    public const ONBOARDING_CREATE = 'platform.salon_onboarding.create';

    public const ONBOARDING_UPDATE = 'platform.salon_onboarding.update';

    public const ONBOARDING_DELETE = 'platform.salon_onboarding.delete';

    public const SUBSCRIPTIONS_MANAGE = 'platform.subscription_plans.view';

    public const SUBSCRIPTIONS_CREATE = 'platform.subscription_plans.create';

    public const SUBSCRIPTIONS_UPDATE = 'platform.subscription_plans.update';

    public const SUBSCRIPTIONS_DELETE = 'platform.subscription_plans.delete';

    public const UPGRADE_REQUESTS_VIEW = 'platform.upgrade_requests.view';

    public const UPGRADE_REQUESTS_UPDATE = 'platform.upgrade_requests.update';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ONBOARDING_MANAGE,
            self::ONBOARDING_CREATE,
            self::ONBOARDING_UPDATE,
            self::ONBOARDING_DELETE,
            self::SUBSCRIPTIONS_MANAGE,
            self::SUBSCRIPTIONS_CREATE,
            self::SUBSCRIPTIONS_UPDATE,
            self::SUBSCRIPTIONS_DELETE,
            self::UPGRADE_REQUESTS_VIEW,
            self::UPGRADE_REQUESTS_UPDATE,
        ];
    }
}
