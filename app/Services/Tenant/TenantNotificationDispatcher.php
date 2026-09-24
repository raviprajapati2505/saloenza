<?php

namespace App\Services\Tenant;

use App\Models\Saloon;
use App\Support\Tenant\TenantConfig;
use Illuminate\Mail\Mailable;

class TenantNotificationDispatcher
{
    public function __construct(
        private readonly TenantConfig $tenantConfig,
        private readonly TenantMailDeliveryService $mailDeliveryService,
    ) {
    }

    public function sendMail(
        Saloon $salon,
        string $recipient,
        Mailable $mailable,
        ?string $notificationKey = null,
        bool $defaultEnabled = true,
    ): bool {
        if ($notificationKey !== null && ! $this->tenantConfig->notificationEnabled($salon, $notificationKey, $defaultEnabled)) {
            return false;
        }

        if ($recipient === '') {
            return false;
        }

        $this->mailDeliveryService->send($salon, $recipient, $mailable);

        return true;
    }

    /**
     * SMS channel removed.
     *
     * TODO: WhatsApp — route phone notifications through WhatsApp when integration is ready.
     */
    public function sendSms(
        Saloon $salon,
        string $phone,
        string $message,
        ?string $notificationKey = null,
        bool $defaultEnabled = true,
    ): bool {
        // TODO: WhatsApp integration — previously dispatched SMS via TenantSmsDeliveryService.
        unset($salon, $phone, $message, $notificationKey, $defaultEnabled);

        return false;
    }
}
