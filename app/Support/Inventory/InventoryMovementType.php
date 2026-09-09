<?php

namespace App\Support\Inventory;

final class InventoryMovementType
{
    public const SALE = 'sale';

    public const PURCHASE = 'purchase';

    public const ADJUSTMENT = 'adjustment';

    public const RETURN = 'return';

    /** @var list<string> */
    public const ALL = [
        self::SALE,
        self::PURCHASE,
        self::ADJUSTMENT,
        self::RETURN,
    ];
}
