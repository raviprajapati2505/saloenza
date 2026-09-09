<?php

namespace App\Support;

class AuthIdentifier
{
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);

        return self::isEmail($trimmed)
            ? strtolower($trimmed)
            : $trimmed;
    }

    public static function isEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isPhone(string $value): bool
    {
        return preg_match('/^\+?[0-9\s\-()]{7,20}$/', trim($value)) === 1;
    }
}
