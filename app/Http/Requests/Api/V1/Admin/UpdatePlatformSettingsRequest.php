<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $group = (string) $this->route('group');
            /** @var PlatformSettingsService $service */
            $service = app(PlatformSettingsService::class);

            if (! isset($service->groupDefinitions()[$group])) {
                $validator->errors()->add('group', 'Unknown settings group.');

                return;
            }

            $definitions = $service->groupDefinitions()[$group]['settings'] ?? [];
            $settings = (array) $this->input('settings', []);

            foreach ($definitions as $key => $definition) {
                if (($definition['type'] ?? '') === 'file') {
                    continue;
                }

                if (($definition['type'] ?? '') === 'secret' && ! array_key_exists($key, $settings)) {
                    continue;
                }

                if (! array_key_exists($key, $settings)) {
                    $validator->errors()->add("settings.{$key}", 'This setting is required.');

                    continue;
                }

                $value = $settings[$key];
                $type = $definition['type'] ?? 'string';

                if ($type === 'number' && ! is_numeric($value)) {
                    $validator->errors()->add("settings.{$key}", 'Must be a number.');
                    continue;
                }

                if ($type === 'boolean' && ! is_bool($value) && ! in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $validator->errors()->add("settings.{$key}", 'Must be true or false.');
                    continue;
                }

                if ($type === 'number') {
                    $numeric = (float) $value;
                    if (isset($definition['min']) && $numeric < (float) $definition['min']) {
                        $validator->errors()->add("settings.{$key}", "Must be at least {$definition['min']}.");
                    }
                    if (isset($definition['max']) && $numeric > (float) $definition['max']) {
                        $validator->errors()->add("settings.{$key}", "Must be at most {$definition['max']}.");
                    }
                }
            }

            foreach ($settings as $key => $value) {
                if (! isset($definitions[$key])) {
                    $validator->errors()->add("settings.{$key}", 'Unknown setting key.');
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedSettings(): array
    {
        $group = (string) $this->route('group');
        /** @var PlatformSettingsService $service */
        $service = app(PlatformSettingsService::class);
        $definitions = $service->groupDefinitions()[$group]['settings'] ?? [];
        $settings = (array) $this->input('settings', []);
        $normalized = [];

        foreach ($definitions as $key => $definition) {
            if (($definition['type'] ?? '') === 'file') {
                continue;
            }

            if (($definition['type'] ?? '') === 'secret' && ! array_key_exists($key, $settings)) {
                continue;
            }

            $value = $settings[$key] ?? null;
            $type = $definition['type'] ?? 'string';

            if (($type === 'secret') && ($value === null || $value === '')) {
                continue;
            }

            $normalized[$key] = match ($type) {
                'number' => ($definition['integer'] ?? false) ? (int) $value : (float) $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => $value === null ? (string) ($definition['default'] ?? '') : $value,
            };
        }

        return $normalized;
    }
}
