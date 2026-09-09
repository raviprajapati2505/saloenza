<?php

namespace App\Support\Saloon;

use App\Models\Saloon;
use Illuminate\Support\Str;

class ReferralCodeGenerator
{
    public static function generate(?string $businessName = null): string
    {
        $prefix = self::prefixFromBusinessName($businessName);

        do {
            $code = $prefix.strtoupper(Str::random(6));
        } while (Saloon::query()->where('referral_code', $code)->exists());

        return $code;
    }

    private static function prefixFromBusinessName(?string $businessName): string
    {
        if ($businessName === null || trim($businessName) === '') {
            return 'REF';
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $businessName) ?? '');

        if ($normalized === '') {
            return 'REF';
        }

        return substr($normalized, 0, 4);
    }
}
