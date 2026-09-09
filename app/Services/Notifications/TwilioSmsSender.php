<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsOtpSenderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSmsSender implements SmsOtpSenderInterface
{
    /**
     * @param  array{account_sid: string, auth_token: string, from: string}  $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function send(string $phone, string $message): void
    {
        $accountSid = trim((string) ($this->config['account_sid'] ?? ''));
        $authToken = trim((string) ($this->config['auth_token'] ?? ''));
        $from = trim((string) ($this->config['from'] ?? ''));

        if ($accountSid === '' || $authToken === '') {
            Log::warning('twilio.sms.skipped', ['reason' => 'missing_credentials', 'phone' => $phone]);

            return;
        }

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", array_filter([
                'To' => $phone,
                'From' => $from !== '' ? $from : null,
                'Body' => $message,
            ]));

        if (! $response->successful()) {
            Log::error('twilio.sms.failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Twilio SMS delivery failed.');
        }
    }
}
