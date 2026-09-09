<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        /** @var array<string, array<string, mixed>> $groups */
        $groups = config('platform_settings.groups', []);

        foreach ($groups as $group => $definition) {
            foreach ($definition['settings'] ?? [] as $key => $meta) {
                if (! array_key_exists('default', $meta)) {
                    continue;
                }

                PlatformSetting::query()->firstOrCreate(
                    ['group' => $group, 'key' => (string) $key],
                    [
                        'value' => $meta['default'],
                        'updated_by' => null,
                    ],
                );
            }
        }
    }
}
