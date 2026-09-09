<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\EmailOtpSenderInterface;
use Aws\Ses\SesClient;

class AwsSesEmailOtpSender implements EmailOtpSenderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function send(string $email, string $subject, string $message): void
    {
        $client = new SesClient($this->clientConfig());

        $client->sendEmail([
            'Source' => $this->source(),
            'Destination' => [
                'ToAddresses' => [$email],
            ],
            'Message' => [
                'Subject' => [
                    'Charset' => 'UTF-8',
                    'Data' => $subject,
                ],
                'Body' => [
                    'Text' => [
                        'Charset' => 'UTF-8',
                        'Data' => $message,
                    ],
                ],
            ],
        ]);
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

    private function source(): string
    {
        $email = (string) ($this->config['ses']['from_email'] ?? '');
        $name = trim((string) ($this->config['ses']['from_name'] ?? ''));

        return $name !== ''
            ? sprintf('%s <%s>', $name, $email)
            : $email;
    }
}
