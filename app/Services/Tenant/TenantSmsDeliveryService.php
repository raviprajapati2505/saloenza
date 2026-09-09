<?php

namespace App\Services\Tenant;

use App\Contracts\Notifications\SmsOtpSenderInterface;
use App\Models\Saloon;
use App\Services\Notifications\AwsSnsSmsOtpSender;
use App\Services\Notifications\LogSmsOtpSender;
use App\Services\Notifications\Msg91SmsSender;
use App\Services\Notifications\TwilioSmsSender;
use App\Support\Tenant\TenantConfig;

class TenantSmsDeliveryService
{
    public function __construct(
        private readonly TenantConfig $tenantConfig,
    ) {
    }

    public function send(Saloon $salon, string $phone, string $message): void
    {
        $this->senderForSalon($salon)->send($phone, $message);
    }

    private function senderForSalon(Saloon $salon): SmsOtpSenderInterface
    {
        $sms = $this->tenantConfig->group($salon, 'sms');
        $useCustom = (bool) ($sms['use_custom'] ?? false);
        $provider = (string) ($sms['provider'] ?? 'platform');

        if (! $useCustom || $provider === 'platform') {
            return match (config('otp.channels.sms.driver', 'log')) {
                'aws' => new AwsSnsSmsOtpSender([
                    ...config('otp.aws', []),
                    'channels' => config('otp.channels', []),
                ]),
                default => new LogSmsOtpSender,
            };
        }

        return match ($provider) {
            'aws_sns' => new AwsSnsSmsOtpSender([
                ...config('otp.aws', []),
                'channels' => [
                    'sms' => [
                        'sender_id' => (string) ($sms['sender_id'] ?? ''),
                        'type' => config('otp.channels.sms.type', 'Transactional'),
                    ],
                ],
                'credentials' => [
                    'key' => config('otp.aws.credentials.key'),
                    'secret' => (string) ($sms['api_key'] ?? config('otp.aws.credentials.secret')),
                ],
            ]),
            'msg91' => new Msg91SmsSender([
                'auth_key' => (string) ($sms['api_key'] ?? ''),
                'sender_id' => (string) ($sms['sender_id'] ?? ''),
            ]),
            'twilio' => new TwilioSmsSender([
                'account_sid' => (string) ($sms['account_sid'] ?? ''),
                'auth_token' => (string) ($sms['api_key'] ?? ''),
                'from' => (string) ($sms['sender_id'] ?? ''),
            ]),
            default => new LogSmsOtpSender,
        };
    }
}
