<?php

namespace App\Services\Tenant;

use App\Models\SalonSetting;
use App\Models\Saloon;
use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantSettingsService
{
    private const CACHE_PREFIX = 'salon_settings.';

    /** @var list<string> */
    private const SECRET_KEYS = ['smtp_password', 'api_key'];

    public function __construct(
        private readonly PlatformSettingsService $platformSettingsService,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function groupDefinitions(): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = config('tenant_settings.groups', []);

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function settingDefinition(string $group, string $key): ?array
    {
        return $this->groupDefinitions()[$group]['settings'][$key] ?? null;
    }

    public function resolve(Saloon $salon, string $group, string $key, mixed $default = null): mixed
    {
        $tenantStored = $this->allStoredSettings($salon->id)[$group][$key] ?? null;

        if ($tenantStored !== null) {
            return $this->decryptIfSecret($key, $tenantStored);
        }

        return $this->platformSettingsService->get(
            $group,
            $key,
            $this->definitionDefault($group, $key, $default),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveGroup(Saloon $salon, string $group): array
    {
        $definitions = $this->groupDefinitions()[$group]['settings'] ?? [];
        $values = [];

        foreach ($definitions as $key => $definition) {
            $values[$key] = $this->resolve($salon, $group, (string) $key, $definition['default'] ?? null);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function brandingPayload(Saloon $salon): array
    {
        $branding = $this->resolveGroup($salon, 'branding');
        $logoPath = $branding['logo_path'] ?? null;

        return [
            'portal_name' => (string) ($branding['portal_name'] ?? config('app.name')),
            'logo_url' => $this->logoUrl($logoPath),
            'primary_color' => (string) ($branding['primary_color'] ?? '#cc0f67'),
            'secondary_color' => (string) ($branding['secondary_color'] ?? '#8f0a48'),
            'support_email' => (string) ($branding['support_email'] ?? ''),
            'support_phone' => (string) ($branding['support_phone'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function regionalPayload(Saloon $salon): array
    {
        $regional = $this->resolveGroup($salon, 'regional');

        return [
            'timezone' => (string) ($regional['timezone'] ?? config('app.timezone', 'Asia/Kolkata')),
            'locale' => (string) ($regional['locale'] ?? 'en_IN'),
            'currency' => (string) ($regional['currency'] ?? 'INR'),
            'date_format' => (string) ($regional['date_format'] ?? 'd M Y'),
            'time_format' => (string) ($regional['time_format'] ?? '12h'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllGroupsPayload(Saloon $salon): array
    {
        return array_values(array_map(
            fn (string $group): array => $this->getGroupPayload($salon, $group),
            array_keys($this->groupDefinitions()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroupPayload(Saloon $salon, string $group): array
    {
        $definition = $this->groupDefinitions()[$group] ?? null;

        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown tenant settings group [{$group}].");
        }

        $tenantStored = $this->allStoredSettings($salon->id)[$group] ?? [];
        $settings = [];

        foreach ($definition['settings'] as $key => $meta) {
            $tenantValue = $tenantStored[$key] ?? null;
            $platformValue = $this->platformSettingsService->get($group, (string) $key, $meta['default'] ?? null);
            $resolved = $tenantValue !== null
                ? $this->decryptIfSecret((string) $key, $tenantValue)
                : $platformValue;

            $settings[$key] = [
                ...$meta,
                'key' => $key,
                'value' => $this->presentValue((string) $key, $resolved),
                'resolved_value' => $this->presentValue((string) $key, $resolved),
                'tenant_value' => $tenantValue !== null
                    ? $this->presentValue((string) $key, $this->decryptIfSecret((string) $key, $tenantValue))
                    : null,
                'platform_value' => $this->presentValue((string) $key, $platformValue),
                'is_custom' => $tenantValue !== null,
                'source' => $tenantValue !== null ? 'tenant' : 'platform',
            ];
        }

        return [
            'group' => $group,
            'label' => $definition['label'] ?? $group,
            'description' => $definition['description'] ?? null,
            'settings' => $settings,
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function updateGroup(Saloon $salon, string $group, array $values, ?User $updatedBy = null): array
    {
        if (! isset($this->groupDefinitions()[$group])) {
            throw new \InvalidArgumentException("Unknown tenant settings group [{$group}].");
        }

        DB::transaction(function () use ($salon, $group, $values, $updatedBy): void {
            foreach ($values as $key => $value) {
                $definition = $this->settingDefinition($group, (string) $key);

                if ($definition === null) {
                    continue;
                }

                if (($definition['type'] ?? '') === 'file') {
                    continue;
                }

                if (($definition['type'] ?? '') === 'secret' && ($value === '' || $value === null)) {
                    continue;
                }

                if ($value === null || $value === '') {
                    SalonSetting::query()
                        ->where('saloon_id', $salon->id)
                        ->where('group', $group)
                        ->where('key', (string) $key)
                        ->delete();

                    continue;
                }

                $storedValue = $this->encryptIfSecret((string) $key, $value);

                SalonSetting::query()->updateOrCreate(
                    [
                        'saloon_id' => $salon->id,
                        'group' => $group,
                        'key' => (string) $key,
                    ],
                    [
                        'value' => $storedValue,
                        'updated_by' => $updatedBy?->id,
                    ],
                );
            }
        });

        Cache::forget(self::CACHE_PREFIX . $salon->id);

        return $this->getGroupPayload($salon, $group);
    }

    public function updateLogo(Saloon $salon, \Illuminate\Http\UploadedFile $file, ?User $updatedBy = null): array
    {
        $existing = $this->allStoredSettings($salon->id)['branding']['logo_path'] ?? null;

        if (is_string($existing) && $existing !== '') {
            Storage::disk('public')->delete($existing);
        }

        $path = $file->store('salon-logos/' . $salon->id, 'public');

        SalonSetting::query()->updateOrCreate(
            [
                'saloon_id' => $salon->id,
                'group' => 'branding',
                'key' => 'logo_path',
            ],
            [
                'value' => $path,
                'updated_by' => $updatedBy?->id,
            ],
        );

        Cache::forget(self::CACHE_PREFIX . $salon->id);

        return $this->getGroupPayload($salon, 'branding');
    }

    public function clearLogo(Saloon $salon): array
    {
        $existing = $this->allStoredSettings($salon->id)['branding']['logo_path'] ?? null;

        if (is_string($existing) && $existing !== '') {
            Storage::disk('public')->delete($existing);
        }

        SalonSetting::query()
            ->where('saloon_id', $salon->id)
            ->where('group', 'branding')
            ->where('key', 'logo_path')
            ->delete();

        Cache::forget(self::CACHE_PREFIX . $salon->id);

        return $this->getGroupPayload($salon, 'branding');
    }

    public function logoUrl(mixed $logoPath): ?string
    {
        if (! is_string($logoPath) || $logoPath === '') {
            $platformLogo = $this->platformSettingsService->get('branding', 'logo_path');

            if (is_string($platformLogo) && $platformLogo !== '') {
                return asset('storage/' . $platformLogo);
            }

            return asset('images/glowsuite-logo.png');
        }

        return asset('storage/' . $logoPath);
    }

    private function definitionDefault(string $group, string $key, mixed $default = null): mixed
    {
        return $this->settingDefinition($group, $key)['default'] ?? $default;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function allStoredSettings(int $salonId): array
    {
        /** @var array<string, array<string, mixed>> $cached */
        $cached = Cache::rememberForever(self::CACHE_PREFIX . $salonId, function () use ($salonId): array {
            $settings = [];

            foreach (SalonSetting::query()->where('saloon_id', $salonId)->get() as $row) {
                $settings[$row->group][$row->key] = $row->value;
            }

            return $settings;
        });

        return $cached;
    }

    private function encryptIfSecret(string $key, mixed $value): mixed
    {
        if (! in_array($key, self::SECRET_KEYS, true) || ! is_string($value) || $value === '') {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    private function decryptIfSecret(string $key, mixed $value): mixed
    {
        if (! in_array($key, self::SECRET_KEYS, true) || ! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function presentValue(string $key, mixed $value): mixed
    {
        if (in_array($key, self::SECRET_KEYS, true) && is_string($value) && $value !== '') {
            return '********';
        }

        if ($key === 'logo_path' && is_string($value) && $value !== '') {
            return $this->logoUrl($value);
        }

        return $value;
    }
}
