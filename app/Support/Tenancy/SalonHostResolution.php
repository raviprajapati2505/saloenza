<?php

namespace App\Support\Tenancy;

use App\Models\Saloon;

final class SalonHostResolution
{
    public const MODE_PLATFORM = 'platform';

    public const MODE_SALON = 'salon';

    public const MODE_MISSING = 'missing';

    private function __construct(
        public readonly string $mode,
        public readonly string $host,
        public readonly ?string $label,
        public readonly ?Saloon $saloon,
    ) {
    }

    public static function platform(string $host): self
    {
        return new self(self::MODE_PLATFORM, $host, null, null);
    }

    public static function salon(string $host, string $label, Saloon $saloon): self
    {
        return new self(self::MODE_SALON, $host, $label, $saloon);
    }

    public static function missing(string $host, ?string $label): self
    {
        return new self(self::MODE_MISSING, $host, $label, null);
    }

    public function isMissing(): bool
    {
        return $this->mode === self::MODE_MISSING;
    }
}
