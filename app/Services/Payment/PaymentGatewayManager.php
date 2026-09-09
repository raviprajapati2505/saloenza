<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Support\Payment\PaymentConfig;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(?string $driver = null): PaymentGatewayInterface
    {
        $driver ??= PaymentConfig::driver();

        return match ($driver) {
            'razorpay' => app(RazorpayPaymentGateway::class),
            'stripe' => app(StripePaymentGateway::class),
            'manual' => app(ManualPaymentGateway::class),
            default => throw new InvalidArgumentException("Unsupported payment driver [{$driver}]."),
        };
    }

    public function activeCheckoutDriver(): PaymentGatewayInterface
    {
        if (PaymentConfig::isGatewayConfigured()) {
            return $this->driver(PaymentConfig::driver());
        }

        return $this->driver('manual');
    }
}
