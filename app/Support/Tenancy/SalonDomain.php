<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;

final class SalonDomain
{
    /**
     * Labels that cannot be assigned to a salon.
     *
     * @return list<string>
     */
    public static function reserved(): array
    {
        return [
            'app',
            'www',
            'api',
            'admin',
            'mail',
            'smtp',
            'ftp',
            'cdn',
            'static',
            'assets',
            'localhost',
            'saloenza',
            'support',
            'help',
            'status',
            'blog',
            'docs',
            'staging',
            'dev',
            'test',
            'demo',
        ];
    }

    public static function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    /**
     * @return array<int, mixed>
     */
    public static function rules(?int $ignoreSaloonId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:63',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(self::reserved()),
            Rule::unique('saloons', 'domain')->ignore($ignoreSaloonId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'domain'): array
    {
        return [
            "{$attribute}.required" => 'Workspace domain is required.',
            "{$attribute}.min" => 'Workspace domain must be at least 3 characters.',
            "{$attribute}.max" => 'Workspace domain must be 63 characters or fewer.',
            "{$attribute}.regex" => 'Workspace domain must be lowercase letters, numbers, and hyphens only.',
            "{$attribute}.not_in" => 'This workspace domain is reserved.',
            "{$attribute}.unique" => 'This workspace domain is already used by another salon.',
        ];
    }
}
