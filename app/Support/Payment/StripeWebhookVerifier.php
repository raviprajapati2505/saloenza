<?php

namespace App\Support\Payment;

final class StripeWebhookVerifier
{
    public static function isConfigured(): bool
    {
        return filled(config('payment.stripe.webhook_secret'));
    }

    public static function verify(string $payload, ?string $signatureHeader, int $toleranceSeconds = 300): bool
    {
        $secret = (string) config('payment.stripe.webhook_secret');

        if ($secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_map('trim', explode('=', $part, 2) + [null, null]);
            if ($key === 't') {
                $timestamp = (int) $value;
            }
            if ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
