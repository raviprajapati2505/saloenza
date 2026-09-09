<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionUpgradeOrder;
use App\Services\Subscription\SubscriptionUpgradeService;
use App\Support\Payment\RazorpayWebhookVerifier;
use App\Support\Payment\StripeWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {
    }

    public function razorpay(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (! RazorpayWebhookVerifier::verify($payload, $signature)) {
            throw new HttpException(400, 'Invalid Razorpay webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?? [];
        $eventName = (string) ($event['event'] ?? '');

        if (! in_array($eventName, ['payment.captured', 'order.paid'], true)) {
            return response()->json(['message' => 'Event ignored.']);
        }

        $entity = $event['payload']['payment']['entity']
            ?? $event['payload']['order']['entity']
            ?? null;

        if (! is_array($entity)) {
            return response()->json(['message' => 'No payment entity found.'], 422);
        }

        $gatewayOrderId = (string) ($entity['order_id'] ?? $entity['id'] ?? '');
        $paymentId = (string) ($entity['id'] ?? '');

        if ($gatewayOrderId === '') {
            return response()->json(['message' => 'Missing gateway order reference.'], 422);
        }

        $order = $this->upgradeService->findAwaitingOrderByGatewayReference(
            SubscriptionUpgradeOrder::CHANNEL_RAZORPAY,
            $gatewayOrderId,
        );

        if ($order === null) {
            return response()->json(['message' => 'Matching upgrade order not found.'], 404);
        }

        $this->upgradeService->fulfillFromWebhook($order, [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => $gatewayOrderId,
            'event' => $eventName,
        ], 'razorpay_webhook');

        return response()->json(['message' => 'Razorpay webhook processed successfully.']);
    }

    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! StripeWebhookVerifier::verify($payload, $signature)) {
            throw new HttpException(400, 'Invalid Stripe webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?? [];
        $eventType = (string) ($event['type'] ?? '');

        if ($eventType !== 'checkout.session.completed') {
            return response()->json(['message' => 'Event ignored.']);
        }

        $session = $event['data']['object'] ?? null;

        if (! is_array($session)) {
            return response()->json(['message' => 'Missing checkout session.'], 422);
        }

        if (($session['payment_status'] ?? '') !== 'paid') {
            return response()->json(['message' => 'Checkout session is not paid.'], 422);
        }

        $sessionId = (string) ($session['id'] ?? '');
        $orderId = (int) ($session['metadata']['upgrade_order_id'] ?? 0);

        $order = $orderId > 0
            ? $this->upgradeService->findAwaitingOrderById($orderId)
            : null;

        if ($order === null && $sessionId !== '') {
            $order = $this->upgradeService->findAwaitingOrderByGatewayReference(
                SubscriptionUpgradeOrder::CHANNEL_STRIPE,
                $sessionId,
            );
        }

        if ($order === null) {
            return response()->json(['message' => 'Matching upgrade order not found.'], 404);
        }

        $this->upgradeService->fulfillFromWebhook($order, [
            'stripe_session_id' => $sessionId,
            'stripe_payment_intent' => (string) ($session['payment_intent'] ?? ''),
            'event' => $eventType,
        ], 'stripe_webhook');

        return response()->json(['message' => 'Stripe webhook processed successfully.']);
    }
}
