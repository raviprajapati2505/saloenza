<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlatformSettingsService
{
    private const CACHE_KEY = 'platform_settings.all';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function groupDefinitions(): array
    {
        /** @var array<string, array<string, mixed>> $platformGroups */
        $platformGroups = config('platform_settings.groups', []);

        /** @var array<string, array<string, mixed>> $tenantGroups */
        $tenantGroups = config('tenant_settings.groups', []);

        $tenantDefaults = array_filter(
            $tenantGroups,
            fn (array $group): bool => (bool) ($group['platform_manageable'] ?? false),
        );

        return array_merge($platformGroups, $tenantDefaults);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function settingDefinition(string $group, string $key): ?array
    {
        return $this->groupDefinitions()[$group]['settings'][$key] ?? null;
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $definitionDefault = $this->settingDefinition($group, $key)['default'] ?? $default;
        $stored = $this->stored($group, $key);

        return $stored ?? $definitionDefault;
    }

    public function stored(string $group, string $key): mixed
    {
        return $this->allStoredSettings()[$group][$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroupValues(string $group): array
    {
        $definitions = $this->groupDefinitions()[$group]['settings'] ?? [];
        $stored = $this->allStoredSettings()[$group] ?? [];
        $values = [];

        foreach ($definitions as $key => $definition) {
            $values[$key] = $stored[$key] ?? ($definition['default'] ?? null);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroupPayload(string $group): array
    {
        $definition = $this->groupDefinitions()[$group] ?? null;

        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown platform settings group [{$group}].");
        }

        $settings = [];

        foreach ($definition['settings'] as $key => $meta) {
            $settings[$key] = [
                ...$meta,
                'key' => $key,
                'value' => $this->presentValue((string) $key, $this->get($group, $key)),
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
     * @return list<array<string, mixed>>
     */
    public function getAllGroupsPayload(): array
    {
        return array_values(array_map(
            fn (string $group): array => $this->getGroupPayload($group),
            array_keys($this->groupDefinitions()),
        ));
    }

    /**
     * @param array<string, mixed> $values
     */
    public function updateGroup(string $group, array $values, ?User $updatedBy = null): array
    {
        if (! isset($this->groupDefinitions()[$group])) {
            throw new \InvalidArgumentException("Unknown platform settings group [{$group}].");
        }

        DB::transaction(function () use ($group, $values, $updatedBy): void {
            foreach ($values as $key => $value) {
                $definition = $this->settingDefinition($group, (string) $key);

                if ($definition === null) {
                    continue;
                }

                if (($definition['type'] ?? '') === 'secret' && ($value === '' || $value === null)) {
                    continue;
                }

                // platform_settings.value is NOT NULL JSON — never persist PHP null.
                if ($value === null) {
                    $value = $definition['default'] ?? '';
                }

                PlatformSetting::query()->updateOrCreate(
                    ['group' => $group, 'key' => (string) $key],
                    [
                        'value' => $value,
                        'updated_by' => $updatedBy?->id,
                    ],
                );
            }
        });

        Cache::forget(self::CACHE_KEY);

        return $this->getGroupPayload($group);
    }

    public function salonReferralCommissionRate(): float
    {
        return (float) $this->get('salon_referrals', 'commission_rate', 10);
    }

    public function salonReferralQualifyingMonths(): int
    {
        return max(1, (int) $this->get('salon_referrals', 'qualifying_months', 6));
    }

    /**
     * @return array<string, mixed>
     */
    public function brandingPayload(): array
    {
        $branding = $this->getGroupValues('branding');

        return [
            'portal_name' => (string) ($branding['portal_name'] ?? config('app.name')),
            'logo_url' => $this->logoUrl($branding['logo_path'] ?? null),
            'primary_color' => (string) ($branding['primary_color'] ?? '#cc0f67'),
            'secondary_color' => (string) ($branding['secondary_color'] ?? '#8f0a48'),
            'support_email' => (string) ($branding['support_email'] ?? ''),
            'support_phone' => (string) ($branding['support_phone'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function regionalPayload(): array
    {
        $regional = $this->getGroupValues('regional');
        $currency = strtoupper((string) ($regional['currency'] ?? 'QAR'));
        if (! in_array($currency, ['QAR', 'USD'], true)) {
            $currency = 'QAR';
        }

        return [
            'timezone' => (string) ($regional['timezone'] ?? config('app.timezone', 'Asia/Qatar')),
            'locale' => (string) ($regional['locale'] ?? 'en_QA'),
            'currency' => $currency,
            'date_format' => (string) ($regional['date_format'] ?? 'd M Y'),
            'time_format' => (string) ($regional['time_format'] ?? '12h'),
        ];
    }

    public function updateLogo(UploadedFile $file, ?User $updatedBy = null): array
    {
        $existing = $this->allStoredSettings()['branding']['logo_path'] ?? null;

        if (is_string($existing) && $existing !== '') {
            Storage::disk('public')->delete($existing);
        }

        $path = $file->store('platform-logos', 'public');

        PlatformSetting::query()->updateOrCreate(
            ['group' => 'branding', 'key' => 'logo_path'],
            [
                'value' => $path,
                'updated_by' => $updatedBy?->id,
            ],
        );

        Cache::forget(self::CACHE_KEY);

        return $this->getGroupPayload('branding');
    }

    public function clearLogo(): array
    {
        $existing = $this->allStoredSettings()['branding']['logo_path'] ?? null;

        if (is_string($existing) && $existing !== '') {
            Storage::disk('public')->delete($existing);
        }

        PlatformSetting::query()
            ->where('group', 'branding')
            ->where('key', 'logo_path')
            ->delete();

        Cache::forget(self::CACHE_KEY);

        return $this->getGroupPayload('branding');
    }

    public function logoUrl(mixed $logoPath): ?string
    {
        if (! is_string($logoPath) || $logoPath === '') {
            return asset('images/glowsuite-logo.png');
        }

        return asset('storage/'.$logoPath);
    }

    private function presentValue(string $key, mixed $value): mixed
    {
        if ($key === 'logo_path' && is_string($value) && $value !== '') {
            return $this->logoUrl($value);
        }

        return $value;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function allStoredSettings(): array
    {
        /** @var array<string, array<string, mixed>> $cached */
        $cached = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $settings = [];

            foreach (PlatformSetting::query()->get() as $row) {
                $settings[$row->group][$row->key] = $row->value;
            }

            return $settings;
        });

        return $cached;
    }
}
