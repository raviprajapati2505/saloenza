<?php

namespace App\Mail\Concerns;

trait UsesTenantBranding
{
    /** @var array<string, mixed> */
    public array $branding = [];

    /**
     * @param  array<string, mixed>  $branding
     */
    public function withBranding(array $branding): static
    {
        $this->branding = $branding;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function brandingViewData(): array
    {
        return [
            'portalName' => (string) ($this->branding['portal_name'] ?? config('app.name')),
            'logoUrl' => $this->branding['logo_url'] ?? null,
            'primaryColor' => (string) ($this->branding['primary_color'] ?? '#E0229A'),
            'supportEmail' => (string) ($this->branding['support_email'] ?? ''),
            'supportPhone' => (string) ($this->branding['support_phone'] ?? ''),
        ];
    }
}
