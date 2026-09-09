<?php

namespace App\Support\Affiliate;

use App\Models\AffiliatePartner;
use Illuminate\Support\Str;

class AffiliateCodeGenerator
{
    public static function generate(?string $seed = null): string
    {
        $prefix = 'AFF-';
        if ($seed !== null && trim($seed) !== '') {
            $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $seed) ?? '');
            if ($normalized !== '') {
                $prefix = 'AFF-'.substr($normalized, 0, 6);
            }
        }

        do {
            $code = $prefix.strtoupper(Str::random(4));
        } while (AffiliatePartner::query()->where('code', $code)->exists());

        return $code;
    }
}
