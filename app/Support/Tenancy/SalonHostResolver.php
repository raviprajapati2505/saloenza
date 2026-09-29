<?php

namespace App\Support\Tenancy;

use App\Models\Saloon;
use Illuminate\Http\Request;

final class SalonHostResolver
{
    public const REQUEST_ATTRIBUTE = 'resolved_saloon';

    public function resolve(Request $request): SalonHostResolution
    {
        return $this->classify($request->getHost());
    }

    public function classify(string $host): SalonHostResolution
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = rtrim($host, '.');

        if ($host === '' || $this->isLocalHost($host)) {
            return SalonHostResolution::platform($host);
        }

        $base = $this->baseDomain();

        if ($base === '' || $host === $base) {
            return SalonHostResolution::platform($host);
        }

        $suffix = '.'.$base;

        if (! str_ends_with($host, $suffix)) {
            return SalonHostResolution::platform($host);
        }

        $label = substr($host, 0, -strlen($suffix));

        if ($label === '' || str_contains($label, '.')) {
            return SalonHostResolution::missing($host, $label !== '' ? $label : null);
        }

        if (in_array($label, $this->platformSubdomains(), true)) {
            return SalonHostResolution::platform($host);
        }

        $saloon = Saloon::query()->where('domain', $label)->first();

        if ($saloon === null) {
            return SalonHostResolution::missing($host, $label);
        }

        return SalonHostResolution::salon($host, $label, $saloon);
    }

    public function loginUrlFor(?Saloon $saloon): string
    {
        $domain = $saloon?->domain;

        if (is_string($domain) && $domain !== '') {
            return $this->urlForDomain($domain).'/login';
        }

        return rtrim((string) config('app.url'), '/').'/login';
    }

    public function urlForDomain(string $domain): string
    {
        return $this->scheme().'://'.$domain.'.'.$this->baseDomain();
    }

    public function platformUrl(): string
    {
        return $this->scheme().'://app.'.$this->baseDomain();
    }

    private function baseDomain(): string
    {
        return strtolower(trim((string) config('tenancy.base_domain')));
    }

    /**
     * @return list<string>
     */
    private function platformSubdomains(): array
    {
        $configured = config('tenancy.platform_subdomains', ['app', 'www']);

        if (! is_array($configured)) {
            return ['app', 'www'];
        }

        return array_values(array_map(
            static fn (mixed $label): string => strtolower(trim((string) $label)),
            $configured,
        ));
    }

    private function scheme(): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? $scheme : 'https';
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }
}
