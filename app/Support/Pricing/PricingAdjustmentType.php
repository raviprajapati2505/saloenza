<?php

namespace App\Support\Pricing;

final class PricingAdjustmentType
{
    public const PERCENT_OFF = 'percent_off';

    public const PERCENT_ON = 'percent_on';

    public const FIXED_PRICE = 'fixed_price';

    public const FIXED_OFF = 'fixed_off';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PERCENT_OFF,
            self::PERCENT_ON,
            self::FIXED_PRICE,
            self::FIXED_OFF,
        ];
    }
}
