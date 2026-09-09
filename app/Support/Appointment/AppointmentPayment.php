<?php

namespace App\Support\Appointment;

final class AppointmentPayment
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_REFUNDED = 'refunded';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_REFUNDED,
    ];

    public const METHOD_CASH = 'cash';

    public const METHOD_CARD = 'card';

    public const METHOD_UPI = 'upi';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_OTHER = 'other';

    /** @var list<string> */
    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_UPI,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_OTHER,
    ];

    public static function resolveStatus(float $amountPaid, float $grandTotal, bool $refunded = false): string
    {
        if ($refunded) {
            return self::STATUS_REFUNDED;
        }

        if ($amountPaid <= 0) {
            return self::STATUS_UNPAID;
        }

        if ($amountPaid + 0.009 >= $grandTotal) {
            return self::STATUS_PAID;
        }

        return self::STATUS_PARTIAL;
    }

    public static function balanceDue(float $grandTotal, float $amountPaid): float
    {
        return max(round($grandTotal - $amountPaid, 2), 0);
    }
}
