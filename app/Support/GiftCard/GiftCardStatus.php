<?php

namespace App\Support\GiftCard;

final class GiftCardStatus
{
    public const ACTIVE = 'active';

    public const REDEEMED = 'redeemed';

    public const EXPIRED = 'expired';

    public const DISABLED = 'disabled';

    /** @var list<string> */
    public const ALL = [
        self::ACTIVE,
        self::REDEEMED,
        self::EXPIRED,
        self::DISABLED,
    ];
}
