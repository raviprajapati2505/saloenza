<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\OtpNotificationServiceInterface;
use App\Models\Saloon;
use App\Services\Tenant\TenantMailDeliveryService;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtpNotificationService implements OtpNotificationServiceInterface
{
    public function __construct(
        private readonly TenantMailDeliveryService $tenantMailDeliveryService,
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
            // TODO: WhatsApp — send password-reset OTP to phone when WhatsApp is integrated.
            'sms' => throw new HttpException(
                422,
                'SMS verification is no longer available. Please reset your password using your email address.',
            ),
            default => throw new InvalidArgumentException('Unsupported OTP channel.'),
        };
    }
}
