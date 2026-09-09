<?php

namespace App\Services\Tenant;

use App\Models\Saloon;
use App\Services\Platform\PlatformSettingsService;
use App\Support\Tenant\TenantConfig;

class TenantMailConfigResolver
{
    public function __construct(
        private readonly TenantConfig $tenantConfig,
        private readonly PlatformSettingsService $platformSettingsService,
    ) {
    }

    /**
     * @return array{
     *     mailer: string,
     *     transport: array<string, mixed>|null,
     *     from: array{name: string, address: string},
     *     reply_to: string|null
     * }
     */
    public function resolve(?Saloon $salon = null): array
    {
        if ($salon === null || ! $this->tenantConfig->shouldUseCustomEmail($salon)) {
            return $this->platformMailConfig();
        }

        $email = $this->tenantConfig->group($salon, 'email');
        $fromName = trim((string) ($email['from_name'] ?? config('mail.from.name', '')));
        $fromEmail = trim((string) ($email['from_email'] ?? config('mail.from.address', '')));
        $transport = $this->smtpTransportFrom($email);

        if ($transport === null) {
            return $this->platformMailConfig(
                $fromName !== '' ? $fromName : null,
                $fromEmail !== '' ? $fromEmail : null,
                $this->nullableString($email['reply_to'] ?? null),
            );
        }

        return [
            'mailer' => 'tenant_dynamic_'.$salon->id,
            'transport' => $transport,
            'from' => [
                'name' => $fromName !== '' ? $fromName : $salon->name,
                'address' => $fromEmail,
            ],
            'reply_to' => $this->nullableString($email['reply_to'] ?? null),
        ];
    }

    /**
     * @return array{
     *     mailer: string,
     *     transport: array<string, mixed>|null,
     *     from: array{name: string, address: string},
     *     reply_to: string|null
     * }
     */
    private function platformMailConfig(
        ?string $fromName = null,
        ?string $fromEmail = null,
        ?string $replyTo = null,
    ): array {
        $email = $this->platformSettingsService->getGroupValues('email');
        $storedHost = trim((string) ($this->platformSettingsService->stored('email', 'smtp_host') ?? ''));
        $transport = $storedHost !== '' ? $this->smtpTransportFrom($email) : null;

        return [
            'mailer' => $transport !== null ? 'platform_smtp' : (string) config('mail.default', 'log'),
            'transport' => $transport,
            'from' => [
                'name' => $fromName
                    ?? $this->nullableString($email['from_name'] ?? null)
                    ?? (string) config('mail.from.name', config('app.name', 'Glowsuite')),
                'address' => $fromEmail
                    ?? $this->nullableString($email['from_email'] ?? null)
                    ?? (string) config('mail.from.address', 'hello@example.com'),
            ],
            'reply_to' => $replyTo ?? $this->nullableString($email['reply_to'] ?? null),
        ];
    }

    /**
     * Laravel 12 Symfony mailer uses `scheme` (smtp / smtps), not the old `encryption` key.
     * Hostinger and other SMTPS providers require smtps on port 465.
     *
     * @param  array<string, mixed>  $email
     * @return array<string, mixed>|null
     */
    private function smtpTransportFrom(array $email): ?array
    {
        $host = trim((string) ($email['smtp_host'] ?? ''));

        if ($host === '') {
            return null;
        }

        $port = (int) ($email['smtp_port'] ?? 587);
        $encryption = strtolower(trim((string) ($email['smtp_encryption'] ?? 'tls')));
        $scheme = match ($encryption) {
            'ssl', 'smtps' => 'smtps',
            'none' => $port === 465 ? 'smtps' : 'smtp',
            default => $port === 465 ? 'smtps' : 'smtp',
        };

        return [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'username' => (string) ($email['smtp_username'] ?? ''),
            'password' => (string) ($email['smtp_password'] ?? ''),
            'timeout' => 30,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }
}
