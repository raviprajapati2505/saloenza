<?php

namespace App\Support\Phone;

final class PhoneNumber
{
    /** E.164-style: + followed by 7–15 digits, first digit 1–9. */
    public const E164_REGEX = '/^\+[1-9]\d{6,14}$/';

    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return '+'.$digits;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== null && (bool) preg_match(self::E164_REGEX, $normalized);
    }
}
