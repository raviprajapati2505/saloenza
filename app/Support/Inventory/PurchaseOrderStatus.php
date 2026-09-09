<?php

namespace App\Support\Inventory;

final class PurchaseOrderStatus
{
    public const DRAFT = 'draft';

    public const ORDERED = 'ordered';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    /** @var list<string> */
    public const ALL = [
        self::DRAFT,
        self::ORDERED,
        self::RECEIVED,
        self::CANCELLED,
    ];
}
