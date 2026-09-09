<?php

namespace App\Services\Pos;

use App\Models\Appointment;
use App\Support\Catalog\SalonOfferingResolver;
use App\Models\User;
use App\Services\Appointment\AppointmentBookingService;
use App\Services\Inventory\BranchStockService;
use App\Support\Appointment\AppointmentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCheckoutService
{
    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly BranchStockService $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkout(User $actor, array $payload): Appointment
    {
        return DB::transaction(function () use ($actor, $payload): Appointment {
            $branchId = isset($payload['branch_id']) ? (int) $payload['branch_id'] : null;
            if ($branchId === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Branch is required for POS checkout.',
                ]);
            }

            $items = $payload['items'] ?? [];
            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one item to the cart.',
                ]);
            }

            $cart = $this->splitCart($items, (int) $actor->saloon_id, $branchId);

            // Only retail sell lines consume stock. A product on a service is a
            // variant of that service, not a quantity leaving the shelf.
            $stockLines = $this->stockLines($cart['products']);
            if ($stockLines !== []) {
                $this->stock->assertAvailable($branchId, $stockLines);
            }

            $bookingPayload = [
                'saloon_id' => (int) $actor->saloon_id,
                'branch_id' => $branchId,
                'customer_id' => $payload['customer_id'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'type' => $cart['services'] === []
                    ? Appointment::TYPE_PRODUCT_SALE
                    : Appointment::TYPE_WALK_IN,
                'booking_source' => $cart['services'] === []
                    ? Appointment::SOURCE_POS
                    : Appointment::SOURCE_WALK_IN,
                'starts_at' => now()->toIso8601String(),
                'status' => $payload['status'] ?? AppointmentStatus::COMPLETED,
                'discount' => (float) ($payload['discount'] ?? 0),
                'notes' => $payload['notes'] ?? null,
                'collect_payment' => (bool) ($payload['collect_payment'] ?? false),
                'payment_method' => $payload['payment_method'] ?? null,
                'amount_paid' => $payload['amount_paid'] ?? null,
                'services' => $cart['services'],
                'products' => $cart['products'],
            ];

            $bookingPayload = $this->booking->applyActorDefaults($actor, $bookingPayload);
            $bookingPayload['customer_id'] = $this->booking->resolveCustomerId(
                $bookingPayload,
                (int) $actor->saloon_id,
                null,
                $actor,
            );

            return $this->booking->create($actor, $bookingPayload);
        });
    }

    /**
     * Split a POS cart into booked service lines and sold retail lines.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{services: list<array<string, mixed>>, products: list<array<string, mixed>>}
     */
    private function splitCart(array $items, int $saloonId, int $branchId): array
    {
        $services = [];
        $products = [];

        foreach (array_values($items) as $index => $item) {
            $kind = (string) ($item['kind'] ?? 'service');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($kind === 'retail') {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Product is required.',
                    ]);
                }

                $products[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $item['price'] ?? null,
                    'staff_id' => $this->optionalId($item['staff_id'] ?? null),
                ];

                continue;
            }

            $serviceId = (int) ($item['service_id'] ?? 0);
            if ($serviceId <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.service_id" => 'Service is required.',
                ]);
            }

            $productId = $this->optionalId($item['product_id'] ?? null);

            $offering = SalonOfferingResolver::find($saloonId, $serviceId, $productId, $branchId);

            if ($offering === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.service_id" => 'Selected service is not available for this salon.',
                ]);
            }

            $services[] = [
                'service_id' => $serviceId,
                'product_id' => $productId,
                'staff_id' => $this->optionalId($item['staff_id'] ?? null),
                'is_staff_locked' => (bool) ($item['is_staff_locked'] ?? false),
                'price' => array_key_exists('price', $item) && $item['price'] !== null
                    ? (float) $item['price']
                    : (float) $offering->price,
                'quantity' => $quantity,
                'duration_minutes' => array_key_exists('duration_minutes', $item) && $item['duration_minutes']
                    ? (int) $item['duration_minutes']
                    : (int) $offering->duration_minutes,
            ];
        }

        return ['services' => $services, 'products' => $products];
    }

    private function optionalId(mixed $value): ?int
    {
        return $value !== null && $value !== '' ? (int) $value : null;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $products
     * @return list<array{product_id: int, quantity: int}>
     */
    private function stockLines(array $products): array
    {
        $totals = [];

        foreach ($products as $line) {
            $productId = (int) $line['product_id'];
            $totals[$productId] = ($totals[$productId] ?? 0) + max(1, (int) ($line['quantity'] ?? 1));
        }

        $lines = [];
        foreach ($totals as $productId => $quantity) {
            $lines[] = ['product_id' => $productId, 'quantity' => $quantity];
        }

        return $lines;
    }
}
