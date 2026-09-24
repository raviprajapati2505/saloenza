<?php

namespace App\Services\Tenant;

use App\Models\Saloon;
use Illuminate\Support\Facades\Log;

class TenantSmsDeliveryService
{
    /**
     * SMS delivery has been removed.
     *
     * TODO: Replace with WhatsApp messaging integration for customer notifications.
     */
    public function send(Saloon $salon, string $phone, string $message): void
    {
        // TODO: WhatsApp — send customer/staff notification to {$phone} for salon #{$salon->id}
        Log::info('SMS delivery skipped; WhatsApp integration pending.', [
            'salon_id' => $salon->id,
            'phone' => $phone,
            'message_preview' => mb_substr($message, 0, 80),
        ]);
    }
}
