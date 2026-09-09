<?php

namespace App\Support\Payment;

final class PaymentConfig
{
    public static function driver(): string
    {
        return (string) config('payment.driver', 'manual');
    }

    public static function currency(): string
    {
        return (string) config('payment.currency', 'INR');
    }

    public static function allowInstantUpgrade(): bool
    {
        return (bool) config('payment.allow_instant_upgrade', false);
    }

    public static function manualInstructions(): string
    {
        return (string) config('payment.manual.instructions', '');
    }

    public static function manualAllowTenantRequests(): bool
    {
        return (bool) config('payment.manual.allow_tenant_requests', true);
    }

    public static function razorpayKeyId(): ?string
    {
        $key = config('payment.razorpay.key_id');

        return filled($key) ? (string) $key : null;
    }

    public static function razorpayKeySecret(): ?string
    {
        $secret = config('payment.razorpay.key_secret');

        return filled($secret) ? (string) $secret : null;
    }

    public static function stripePublishableKey(): ?string
    {
        $key = config('payment.stripe.publishable_key');

        return filled($key) ? (string) $key : null;
    }

    public static function stripeSecretKey(): ?string
    {
        $secret = config('payment.stripe.secret_key');

        return filled($secret) ? (string) $secret : null;
    }

    public static function isRazorpayConfigured(): bool
    {
        return self::razorpayKeyId() !== null && self::razorpayKeySecret() !== null;
    }

    public static function isStripeConfigured(): bool
    {
        return self::stripeSecretKey() !== null;
    }

    public static function offlineOnly(): bool
    {
        return (bool) config('payment.offline_only', true);
    }

    public static function isGatewayConfigured(): bool
    {
        if (self::offlineOnly()) {
            return false;
        }

        return match (self::driver()) {
            'razorpay' => self::isRazorpayConfigured(),
            'stripe' => self::isStripeConfigured(),
            default => false,
        };
    }

    public static function companyName(): string
    {
        return match (self::driver()) {
            'stripe' => (string) config('payment.stripe.company_name', config('app.name')),
            default => (string) config('payment.razorpay.company_name', config('app.name')),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicConfig(): array
    {
        $driver = self::driver();
        $gatewayConfigured = self::isGatewayConfigured();

        return [
            'driver' => self::offlineOnly() ? 'manual' : $driver,
            'currency' => self::currency(),
            'gateway_configured' => $gatewayConfigured,
            'offline_only' => self::offlineOnly(),
            'allow_instant_upgrade' => self::allowInstantUpgrade(),
            'manual_mode' => ! $gatewayConfigured || self::offlineOnly(),
            'manual_allow_tenant_requests' => self::manualAllowTenantRequests(),
            'manual_instructions' => self::manualInstructions(),
            'razorpay_key_id' => $gatewayConfigured && $driver === 'razorpay' ? self::razorpayKeyId() : null,
            'stripe_publishable_key' => $gatewayConfigured && $driver === 'stripe' ? self::stripePublishableKey() : null,
            'company_name' => self::companyName(),
        ];
    }
}
