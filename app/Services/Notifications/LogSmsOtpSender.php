<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsOtpSenderInterface;
use Illuminate\Support\Facades\Log;

class LogSmsOtpSender implements SmsOtpSenderInterface
{
    public function send(string $phone, string $message): void
    {
        Log::info('OTP SMS notification queued in log driver.', [
            'channel' => 'sms',
            'destination' => $phone,
            'message' => $message,
        ]);
    }
}
