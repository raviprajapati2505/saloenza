<?php

namespace App\Services\Tenant;

use App\Models\Saloon;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class TenantMailDeliveryService
{
    public function __construct(
        private readonly TenantMailConfigResolver $mailConfigResolver,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    public function send(Saloon $salon, string $recipient, Mailable $mailable): void
    {
        $config = $this->configureMailer($salon);
        $mailer = $config['mailer'];

        $pending = Mail::mailer($mailer)->to($recipient);

        if ($config['reply_to'] !== null && method_exists($pending, 'replyTo')) {
            $pending->replyTo($config['reply_to']);
        }

        $branding = $this->tenantSettingsService->brandingPayload($salon);
        if (method_exists($mailable, 'withBranding')) {
            $mailable->withBranding($branding);
        }

        if ($config['from']['address'] !== '' && method_exists($mailable, 'from')) {
            $mailable->from($config['from']['address'], $config['from']['name']);
        }

        $pending->send($mailable);
    }

    public function sendPlainText(?Saloon $salon, string $recipient, string $subject, string $body): void
    {
        $config = $this->configureMailer($salon);
        $mailer = $config['mailer'];

        Mail::mailer($mailer)->raw($body, function ($message) use ($recipient, $subject, $config): void {
            $message->to($recipient)->subject($subject);

            if ($config['from']['address'] !== '') {
                $message->from($config['from']['address'], $config['from']['name']);
            }

            if ($config['reply_to'] !== null) {
                $message->replyTo($config['reply_to']);
            }
        });
    }

    /**
     * @return array{
     *     mailer: string,
     *     transport: array<string, mixed>|null,
     *     from: array{name: string, address: string},
     *     reply_to: string|null
     * }
     */
    private function configureMailer(?Saloon $salon): array
    {
        $config = $this->mailConfigResolver->resolve($salon);
        $mailer = $config['mailer'];

        if ($config['transport'] !== null) {
            Config::set("mail.mailers.{$mailer}", $config['transport']);
            Mail::purge($mailer);
        }

        return $config;
    }
}
