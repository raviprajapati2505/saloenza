<?php

namespace App\Contracts\Notifications;

use App\Models\Saloon;

interface OtpNotificationServiceInterface
{
    public function sendPasswordResetOtp(
        string $channel,
        string $destination,
        string $code,
        ?Saloon $salon = null,
    ): void;
}
