<?php

namespace App\Support\Tenant;

use App\Models\Saloon;
use App\Services\Tenant\TenantSettingsService;

/**
 * Resolves effective tenant configuration with platform-default fallback.
 */
class TenantConfig
{
    public function __construct(
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    public function get(Saloon $salon, string $group, string $key, mixed $default = null): mixed
    {
        return $this->tenantSettingsService->resolve($salon, $group, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function group(Saloon $salon, string $group): array
    {
        return $this->tenantSettingsService->resolveGroup($salon, $group);
    }

    /**
     * @return array<string, mixed>
     */
    public function branding(Saloon $salon): array
    {
        return $this->tenantSettingsService->brandingPayload($salon);
    }

    public function shouldUseCustomEmail(Saloon $salon): bool
    {
        // Email is always delivered via .env / Brevo — salon SMTP overrides are disabled.
        return false;
    }

    public function shouldUseCustomSms(Saloon $salon): bool
    {
        // SMS delivery removed — future WhatsApp integration will replace it.
        return false;
    }

    public function notificationEnabled(Saloon $salon, string $key, bool $default = true): bool
    {
        return (bool) $this->get($salon, 'notifications', $key, $default);
    }
}
