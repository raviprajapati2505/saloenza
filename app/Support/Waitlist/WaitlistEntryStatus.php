<?php

namespace App\Support\Waitlist;

final class WaitlistEntryStatus
{
    public const WAITING = 'waiting';

    public const OFFERED = 'offered';

    public const BOOKED = 'booked';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::WAITING,
            self::OFFERED,
            self::BOOKED,
            self::EXPIRED,
            self::CANCELLED,
        ];
    }
}
