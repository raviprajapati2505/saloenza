<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\SubscriptionUpgradeOrder;
use App\Services\Tenant\TenantSettingsService;
use App\Support\Payment\PaymentConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayPaymentGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://api.razorpay.com/v1';

    public function __construct(
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    public function driver(): string
    {
        return SubscriptionUpgradeOrder::CHANNEL_RAZORPAY;
    }

    public function isConfigured(): bool
    {
        return PaymentConfig::isRazorpayConfigured();
    }

    public function createCheckout(SubscriptionUpgradeOrder $order): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $amountPaise = (int) round(((float) $order->amount) * 100);

        if ($amountPaise <= 0) {
            throw new RuntimeException('Upgrade amount must be greater than zero for online payment.');
        }

        $response = Http::withBasicAuth(
            (string) PaymentConfig::razorpayKeyId(),
            (string) PaymentConfig::razorpayKeySecret(),
        )->post(self::API_BASE.'/orders', [
            'amount' => $amountPaise,
            'currency' => $order->currency,
            'receipt' => 'upgrade_'.$order->id,
            'notes' => [
                'upgrade_order_id' => (string) $order->id,
                'saloon_id' => (string) $order->saloon_id,
                'plan_id' => (string) $order->to_subscription_plan_id,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.description') ?? 'Unable to create Razorpay order.');
        }

        $gatewayOrder = $response->json();
        $order->loadMissing('saloon');
        $branding = $order->saloon
            ? $this->tenantSettingsService->brandingPayload($order->saloon)
            : app(\App\Services\Platform\PlatformSettingsService::class)->brandingPayload();

        return [
            'mode' => 'razorpay',
            'order_id' => $order->id,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'razorpay_order_id' => $gatewayOrder['id'] ?? null,
            'razorpay_key_id' => PaymentConfig::razorpayKeyId(),
            'company_name' => (string) ($branding['portal_name'] ?? config('app.name')),
            'primary_color' => (string) ($branding['primary_color'] ?? '#cc0f67'),
            'description' => 'Subscription plan upgrade',
        ];
    }

    public function verifyPayment(SubscriptionUpgradeOrder $order, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $gatewayOrderId = (string) ($payload['razorpay_order_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature'] ?? '');

        if ($paymentId === '' || $gatewayOrderId === '' || $signature === '') {
            return false;
        }

        if ($order->gateway_order_id !== null && $order->gateway_order_id !== $gatewayOrderId) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $gatewayOrderId.'|'.$paymentId,
            (string) PaymentConfig::razorpayKeySecret(),
        );

        return hash_equals($expected, $signature);
    }
}
