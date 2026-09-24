<?php

namespace App\Support\NoShow;

final class DepositStatus
{
    public const NOT_REQUIRED = 'not_required';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const WAIVED = 'waived';

    public const REFUNDED = 'refunded';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::NOT_REQUIRED,
            self::PENDING,
            self::PAID,
            self::WAIVED,
            self::REFUNDED,
        ];
    }
}
