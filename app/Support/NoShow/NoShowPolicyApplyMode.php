<?php

namespace App\Support\NoShow;

final class NoShowPolicyApplyMode
{
    public const ALL_CLIENTS = 'all_clients';

    public const TAGGED_ONLY = 'tagged_only';

    public const REPEAT_OFFENDERS = 'repeat_offenders';

    public const HIGH_RISK = 'high_risk';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ALL_CLIENTS,
            self::TAGGED_ONLY,
            self::REPEAT_OFFENDERS,
            self::HIGH_RISK,
        ];
    }
}
