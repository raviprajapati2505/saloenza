<?php

namespace App\Support\Payment;

final class RazorpayWebhookVerifier
{
    public static function isConfigured(): bool
    {
        return filled(config('payment.razorpay.webhook_secret'));
    }

    public static function verify(string $payload, ?string $signature): bool
    {
        $secret = (string) config('payment.razorpay.webhook_secret');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
