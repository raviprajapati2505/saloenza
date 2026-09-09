<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\EmailOtpSenderInterface;
use Illuminate\Support\Facades\Log;

class LogEmailOtpSender implements EmailOtpSenderInterface
{
    public function send(string $email, string $subject, string $message): void
    {
        Log::info('OTP email notification queued in log driver.', [
            'channel' => 'email',
            'destination' => $email,
            'subject' => $subject,
            'message' => $message,
        ]);
    }
}
