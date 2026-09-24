<?php

namespace App\Support\Package;

final class CustomerPackageStatus
{
    public const ACTIVE = 'active';

    public const EXHAUSTED = 'exhausted';

    public const EXPIRED = 'expired';

    public const VOID = 'void';

    /** @var list<string> */
    public const ALL = [
        self::ACTIVE,
        self::EXHAUSTED,
        self::EXPIRED,
        self::VOID,
    ];
}
