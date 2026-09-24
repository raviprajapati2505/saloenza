<?php

namespace App\Services\Tenant;

use App\Models\Saloon;
use RuntimeException;

/**
 * Resolves outbound mail strictly from .env.
 * When BREVO_API_KEY is set, every send uses the Brevo HTTP API transport.
 */
class TenantMailConfigResolver
{
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
        $brevoKey = trim((string) config('services.brevo.key', ''));

        if ($brevoKey === '' && app()->environment('production')) {
            throw new RuntimeException(
                'BREVO_API_KEY is not configured. Set it in .env to send email via Brevo API.'
            );
        }

        return [
            // Prefer Brevo API whenever the key is present, regardless of MAIL_MAILER.
            'mailer' => $brevoKey !== '' ? 'brevo' : (string) config('mail.default', 'log'),
            'transport' => null,
            'from' => [
                'name' => (string) config('mail.from.name', config('app.name', 'Saloenza')),
                'address' => (string) config('mail.from.address', 'hello@example.com'),
            ],
            'reply_to' => $this->nullableString(config('mail.reply_to.address') ?? config('mail.reply_to')),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }
}
