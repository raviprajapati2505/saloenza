<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsOtpSenderInterface;
use Aws\Sns\SnsClient;

class AwsSnsSmsOtpSender implements SmsOtpSenderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function send(string $phone, string $message): void
    {
        $attributes = array_filter([
            'AWS.SNS.SMS.SenderID' => $this->messageAttribute(
                (string) ($this->config['channels']['sms']['sender_id'] ?? ''),
            ),
            'AWS.SNS.SMS.SMSType' => $this->messageAttribute(
                (string) ($this->config['channels']['sms']['type'] ?? 'Transactional'),
            ),
        ]);

        $client = new SnsClient($this->clientConfig());

        $payload = [
            'PhoneNumber' => $phone,
            'Message' => $message,
        ];

        if ($attributes !== []) {
            $payload['MessageAttributes'] = $attributes;
        }

        $client->publish($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientConfig(): array
    {
        $credentials = array_filter([
            'key' => $this->config['credentials']['key'] ?? null,
            'secret' => $this->config['credentials']['secret'] ?? null,
        ]);

        return array_filter([
            'version' => $this->config['version'] ?? 'latest',
            'region' => $this->config['region'] ?? 'us-east-1',
            'credentials' => $credentials !== [] ? $credentials : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>|null
     */
    private function messageAttribute(string $value): ?array
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return [
            'DataType' => 'String',
            'StringValue' => $trimmed,
        ];
    }
}
