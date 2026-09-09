<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\SubscriptionUpgradeOrder;
use App\Support\Payment\PaymentConfig;

class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function driver(): string
    {
        return SubscriptionUpgradeOrder::CHANNEL_MANUAL;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCheckout(SubscriptionUpgradeOrder $order): array
    {
        return [
            'mode' => 'manual',
            'order_id' => $order->id,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'instructions' => PaymentConfig::manualInstructions(),
            'message' => 'Upgrade request submitted. A platform admin will confirm after offline payment.',
        ];
    }

    public function verifyPayment(SubscriptionUpgradeOrder $order, array $payload): bool
    {
        return false;
    }
}
