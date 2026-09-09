<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\SubscriptionUpgradeOrder;
use App\Support\Payment\PaymentConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePaymentGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function driver(): string
    {
        return SubscriptionUpgradeOrder::CHANNEL_STRIPE;
    }

    public function isConfigured(): bool
    {
        return PaymentConfig::isStripeConfigured();
    }

    public function createCheckout(SubscriptionUpgradeOrder $order): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $amountMinor = (int) round(((float) $order->amount) * 100);

        if ($amountMinor <= 0) {
            throw new RuntimeException('Upgrade amount must be greater than zero for online payment.');
        }

        $successUrl = rtrim((string) config('payment.stripe.success_url'), '?&');
        $cancelUrl = rtrim((string) config('payment.stripe.cancel_url'), '?&');

        $response = Http::withToken((string) PaymentConfig::stripeSecretKey())
            ->asForm()
            ->post(self::API_BASE.'/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl.(str_contains($successUrl, '?') ? '&' : '?').'upgrade=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl.(str_contains($cancelUrl, '?') ? '&' : '?').'upgrade=cancelled',
                'client_reference_id' => (string) $order->id,
                'metadata[upgrade_order_id]' => (string) $order->id,
                'metadata[saloon_id]' => (string) $order->saloon_id,
                'metadata[plan_id]' => (string) $order->to_subscription_plan_id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($order->currency),
                'line_items[0][price_data][unit_amount]' => $amountMinor,
                'line_items[0][price_data][product_data][name]' => 'Subscription plan upgrade',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?? 'Unable to create Stripe checkout session.');
        }

        $session = $response->json();

        return [
            'mode' => 'stripe',
            'order_id' => $order->id,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'stripe_session_id' => $session['id'] ?? null,
            'checkout_url' => $session['url'] ?? null,
            'company_name' => (string) config('payment.stripe.company_name', config('app.name')),
            'description' => 'Subscription plan upgrade',
        ];
    }

    public function verifyPayment(SubscriptionUpgradeOrder $order, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $sessionId = (string) ($payload['stripe_session_id'] ?? '');

        if ($sessionId === '') {
            return false;
        }

        $session = $this->retrieveCheckoutSession($sessionId);

        if (($session['payment_status'] ?? '') !== 'paid') {
            return false;
        }

        if ($order->gateway_order_id !== null && $order->gateway_order_id !== $sessionId) {
            return false;
        }

        $metadataOrderId = (string) ($session['metadata']['upgrade_order_id'] ?? '');

        return $metadataOrderId === (string) $order->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $response = Http::withToken((string) PaymentConfig::stripeSecretKey())
            ->get(self::API_BASE.'/checkout/sessions/'.$sessionId);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?? 'Unable to retrieve Stripe checkout session.');
        }

        return $response->json();
    }
}
