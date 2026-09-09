<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\OtpNotificationServiceInterface;
use App\Contracts\Notifications\SmsOtpSenderInterface;
use App\Models\Saloon;
use App\Services\Tenant\TenantMailDeliveryService;
use App\Services\Tenant\TenantSmsDeliveryService;
use InvalidArgumentException;

class OtpNotificationService implements OtpNotificationServiceInterface
{
    public function __construct(
        private readonly SmsOtpSenderInterface $smsSender,
        private readonly TenantMailDeliveryService $tenantMailDeliveryService,
        private readonly TenantSmsDeliveryService $tenantSmsDeliveryService,
    ) {}

    public function sendPasswordResetOtp(
        string $channel,
        string $destination,
        string $code,
        ?Saloon $salon = null,
    ): void {
        $expiryMinutes = (int) config('otp.expiry_minutes', 10);
        $message = sprintf(
            'Your password reset OTP is %s. It will expire in %d minutes.',
            $code,
            $expiryMinutes,
        );

        match ($channel) {
            'email' => $this->tenantMailDeliveryService->sendPlainText(
                $salon,
                $destination,
                'Password reset OTP',
                $message,
            ),
            'sms' => $salon !== null
                ? $this->tenantSmsDeliveryService->send($salon, $destination, $message)
                : $this->smsSender->send($destination, $message),
            default => throw new InvalidArgumentException('Unsupported OTP channel.'),
        };
    }
}
