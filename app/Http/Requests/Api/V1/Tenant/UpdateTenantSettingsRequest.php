<?php

namespace App\Http\Requests\Api\V1\Tenant;

use App\Services\Tenant\TenantSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTenantSettingsRequest extends FormRequest
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
            /** @var TenantSettingsService $service */
            $service = app(TenantSettingsService::class);

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

                if ($type === 'boolean' && ! is_bool($value) && ! in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $validator->errors()->add("settings.{$key}", 'Must be true or false.');
                }

                if ($type === 'number' && $value !== '' && $value !== null && ! is_numeric($value)) {
                    $validator->errors()->add("settings.{$key}", 'Must be a number.');
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
        /** @var TenantSettingsService $service */
        $service = app(TenantSettingsService::class);
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

            $normalized[$key] = match ($type) {
                'number' => ($definition['integer'] ?? false) ? (int) $value : (float) $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => $value,
            };
        }

        return $normalized;
    }
}
