<?php

namespace App\Support\GiftCard;

final class GiftCardTransactionType
{
    public const ISSUE = 'issue';

    public const REDEEM = 'redeem';

    public const ADJUST = 'adjust';

    public const EXPIRE = 'expire';

    /** @var list<string> */
    public const ALL = [
        self::ISSUE,
        self::REDEEM,
        self::ADJUST,
        self::EXPIRE,
    ];
}
