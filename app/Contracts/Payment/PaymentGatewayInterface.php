<?php

namespace App\Contracts\Payment;

use App\Models\SubscriptionUpgradeOrder;

interface PaymentGatewayInterface
{
    public function driver(): string;

    public function isConfigured(): bool;

    /**
     * @return array<string, mixed>
     */
    public function createCheckout(SubscriptionUpgradeOrder $order): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function verifyPayment(SubscriptionUpgradeOrder $order, array $payload): bool;
}
