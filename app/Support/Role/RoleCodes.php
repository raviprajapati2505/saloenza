<?php

namespace App\Support\Role;

final class RoleCodes
{
    public const PLATFORM_SUPER_ADMIN = 'platform.super_admin';

    public const AFFILIATE_PARTNER = 'affiliate.partner';

    public const SALON_FRANCHISE_OWNER = 'salon.franchise_owner';

    public const SALON_FRANCHISE_MANAGER = 'salon.franchise_manager';

    public const SALON_BRANCH_MANAGER = 'salon.branch_manager';

    public const SALON_STAFF = 'salon.staff';

    /** @deprecated Use SALON_FRANCHISE_OWNER */
    public const LEGACY_SALOON_OWNER = 'saloon_owner';

    /**
     * @return list<string>
     */
    public static function systemCodes(): array
    {
        return [
            self::PLATFORM_SUPER_ADMIN,
            self::AFFILIATE_PARTNER,
            self::SALON_FRANCHISE_OWNER,
            self::SALON_FRANCHISE_MANAGER,
            self::SALON_BRANCH_MANAGER,
            self::SALON_STAFF,
        ];
    }

    public static function franchiseOwnerCode(): string
    {
        return self::SALON_FRANCHISE_OWNER;
    }
}
