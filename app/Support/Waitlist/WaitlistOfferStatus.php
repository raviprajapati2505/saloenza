<?php

namespace App\Support\Waitlist;

final class WaitlistOfferStatus
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    public const REVOKED = 'revoked';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::ACCEPTED,
            self::DECLINED,
            self::EXPIRED,
            self::REVOKED,
        ];
    }
}
