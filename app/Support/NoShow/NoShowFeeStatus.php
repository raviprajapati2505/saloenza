<?php

namespace App\Support\NoShow;

final class NoShowFeeStatus
{
    public const NA = 'n/a';

    public const PENDING = 'pending';

    public const CHARGED = 'charged';

    public const FAILED = 'failed';

    public const WAIVED = 'waived';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::NA,
            self::PENDING,
            self::CHARGED,
            self::FAILED,
            self::WAIVED,
        ];
    }
}
