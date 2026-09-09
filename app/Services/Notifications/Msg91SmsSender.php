<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsOtpSenderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Msg91SmsSender implements SmsOtpSenderInterface
{
    /**
     * @param  array{auth_key: string, sender_id: string}  $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function send(string $phone, string $message): void
    {
        $authKey = trim((string) ($this->config['auth_key'] ?? ''));
        $senderId = trim((string) ($this->config['sender_id'] ?? ''));

        if ($authKey === '') {
            Log::warning('msg91.sms.skipped', ['reason' => 'missing_auth_key', 'phone' => $phone]);

            return;
        }

        $mobile = preg_replace('/\D+/', '', $phone) ?? $phone;

        $response = Http::asForm()->post('https://control.msg91.com/api/sendhttp.php', [
            'authkey' => $authKey,
            'mobiles' => $mobile,
            'message' => $message,
            'sender' => $senderId !== '' ? $senderId : 'SALONOS',
            'route' => 4,
            'country' => 91,
        ]);

        if (! $response->successful()) {
            Log::error('msg91.sms.failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('MSG91 SMS delivery failed.');
        }
    }
}
