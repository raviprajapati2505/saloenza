<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\SalonServiceProduct;
use App\Models\User;
use App\Services\Inventory\BranchStockService;
use App\Services\Inventory\RetailProductService;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Appointment\AppointmentStatus;
use App\Support\Branch\BranchScope;
use App\Support\Catalog\SalonOfferingResolver;
use App\Support\Inventory\InventoryMovementType;
use App\Support\Role\RoleCodes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBookingService
{
    public function __construct(
        private readonly StaffAvailabilityChecker $availability,
        private readonly AppointmentPaymentService $payments,
        private readonly BranchStockService $stock,
        private readonly RetailProductService $retail,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Appointment
    {
        return DB::transaction(function () use ($actor, $payload): Appointment {
            $saloonId = (int) $payload['saloon_id'];
            $branchId = isset($payload['branch_id']) ? (int) $payload['branch_id'] : null;

            $services = $this->normalizeServices($payload['services'] ?? [], $saloonId, $branchId);
            $products = $this->normalizeProducts($payload['products'] ?? [], $saloonId, $branchId);
            $this->assertHasLines($services, $products);

            $timeline = $this->buildTimeline($payload, $services);

            $this->assertStaffBelongToBranch($services, $branchId);
            $this->assertProductStaffBelongToBranch($products, $branchId);
            $this->availability->assertAvailable($timeline['lines']);

            $status = filled($payload['status'] ?? null)
                ? (string) $payload['status']
                : AppointmentStatus::defaultForCreate($payload, $services);
            $type = $payload['type'] ?? $this->defaultTypeFor($services);
            AppointmentStatus::assertValidForTimeline($status, $timeline['starts_at'], $type);

            $servicesTotal = $this->servicesTotal($services);
            $productsTotal = $this->productsTotal($products);
            $price = round($servicesTotal + $productsTotal, 2);
            $discount = (float) ($payload['discount'] ?? 0);
            $grandTotal = max($price - $discount, 0);
            $primary = $services[0] ?? null;

            $appointment = Appointment::query()->create([
                'saloon_id' => $saloonId,
                'branch_id' => $payload['branch_id'] ?? null,
                'customer_id' => $payload['customer_id'] ?? null,
                'staff_id' => $primary['staff_id'] ?? ($products[0]['staff_id'] ?? null),
                'service_id' => $primary['service_id'] ?? null,
                'product_id' => $primary['product_id'] ?? ($products[0]['product_id'] ?? null),
                'starts_at' => $timeline['starts_at'],
                'ends_at' => $timeline['ends_at'],
                'status' => $status,
                'type' => $type,
                'booking_source' => $this->resolveBookingSource($payload),
                'price' => $price,
                'services_total' => $servicesTotal,
                'products_total' => $productsTotal,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor->id,
                ...$this->payments->fieldsForCreate($payload, $grandTotal),
            ]);

            $this->syncServiceLines($appointment, $timeline['lines']);
            $this->syncProductLines($appointment, $products);

            $appointment = $appointment->fresh($this->relations());
            $this->maybeDeductStock($appointment, $actor);

            return $appointment;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Appointment $appointment, array $payload, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $payload, $actor): Appointment {
            $previousStatus = $appointment->status;
            $saloonId = (int) $appointment->saloon_id;
            $branchId = array_key_exists('branch_id', $payload)
                ? ($payload['branch_id'] !== null ? (int) $payload['branch_id'] : null)
                : ($appointment->branch_id !== null ? (int) $appointment->branch_id : null);

            $services = $this->normalizeServices($payload['services'] ?? [], $saloonId, $branchId);
            $services = $this->preserveExistingLineTimes($appointment, $services);
            $products = $this->normalizeProducts($payload['products'] ?? [], $saloonId, $branchId);
            $this->assertHasLines($services, $products);

            $timeline = $this->buildTimeline($payload, $services);

            $this->assertStaffBelongToBranch($services, $branchId);
            $this->assertProductStaffBelongToBranch($products, $branchId);

            if ($this->shouldAssertStaffAvailability($appointment, $payload, $timeline['lines'])) {
                $this->availability->assertAvailable($timeline['lines'], (int) $appointment->id);
            }

            $nextStatus = (string) ($payload['status'] ?? $appointment->status);
            $nextType = (string) ($payload['type'] ?? $appointment->type ?? Appointment::TYPE_APPOINTMENT);
            AppointmentStatus::assertValidForTimeline($nextStatus, $timeline['starts_at'], $nextType);

            $servicesTotal = $this->servicesTotal($services);
            $productsTotal = $this->productsTotal($products);
            $price = round($servicesTotal + $productsTotal, 2);
            $discount = (float) ($payload['discount'] ?? 0);
            $grandTotal = max($price - $discount, 0);
            $primary = $services[0] ?? null;
            $paymentFields = $this->payments->fieldsForUpdate($appointment, $payload, $grandTotal);

            $appointment->update([
                'branch_id' => $payload['branch_id'] ?? null,
                'customer_id' => $payload['customer_id'] ?? $appointment->customer_id,
                'staff_id' => $primary['staff_id'] ?? ($products[0]['staff_id'] ?? null),
                'service_id' => $primary['service_id'] ?? null,
                'product_id' => $primary['product_id'] ?? ($products[0]['product_id'] ?? null),
                'starts_at' => $timeline['starts_at'],
                'ends_at' => $timeline['ends_at'],
                'status' => $nextStatus,
                'type' => $nextType,
                'price' => $price,
                'services_total' => $servicesTotal,
                'products_total' => $productsTotal,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'notes' => $payload['notes'] ?? null,
                ...$paymentFields,
            ]);

            if ($paymentFields === [] && $appointment->payment_status !== AppointmentPayment::STATUS_REFUNDED) {
                $paid = (float) $appointment->amount_paid;
                $status = AppointmentPayment::resolveStatus($paid, $grandTotal);
                if ($status !== $appointment->payment_status) {
                    $appointment->update([
                        'payment_status' => $status,
                        'paid_at' => $status === AppointmentPayment::STATUS_PAID ? ($appointment->paid_at ?? now()) : null,
                    ]);
                }
            }

            $appointment->services()->delete();
            $appointment->products()->delete();
            $this->syncServiceLines($appointment, $timeline['lines']);
            $this->syncProductLines($appointment, $products);

            $appointment = $appointment->fresh($this->relations());
            $this->maybeDeductStock($appointment, $actor, $previousStatus);

            return $appointment;
        });
    }

    /**
     * Apply role-based defaults/locks for branch, staff, and walk-in date.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyActorDefaults(User $actor, array $payload): array
    {
        if ($actor->isBranchScopedActor() && $actor->branch_id) {
            $payload['branch_id'] = (int) $actor->branch_id;
        }

        if ($actor->hasRoleCode(RoleCodes::SALON_STAFF)) {
            $payload['staff_id'] = (int) $actor->id;
            foreach (['services', 'products'] as $group) {
                if (empty($payload[$group]) || ! is_array($payload[$group])) {
                    continue;
                }

                foreach ($payload[$group] as $index => $line) {
                    $payload[$group][$index]['staff_id'] = (int) $actor->id;
                }
            }
        }

        if (in_array($payload['type'] ?? null, [Appointment::TYPE_WALK_IN, Appointment::TYPE_PRODUCT_SALE], true)) {
            $startsAt = isset($payload['starts_at'])
                ? Carbon::parse($payload['starts_at'])
                : now();

            if (! $startsAt->isToday()) {
                $startsAt = now();
            }

            $payload['starts_at'] = $startsAt->toIso8601String();
        }

        return $payload;
    }

    public function assertCanMutate(User $actor): void
    {
        if ($actor->grantsAllPermissions()) {
            throw ValidationException::withMessages([
                'appointment' => 'Platform administrators can view appointments but cannot create or edit them.',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    private function assertStaffBelongToBranch(array $services, ?int $branchId): void
    {
        foreach ($services as $service) {
            $staffId = isset($service['staff_id']) ? (int) $service['staff_id'] : null;
            BranchScope::ensureStaffBelongsToBranch($staffId, $branchId);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function assertProductStaffBelongToBranch(array $products, ?int $branchId): void
    {
        foreach ($products as $product) {
            BranchScope::ensureStaffBelongsToBranch($product['staff_id'] ?? null, $branchId);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $services
     * @param  list<array<string, mixed>>  $products
     */
    private function assertHasLines(array $services, array $products): void
    {
        if ($services !== [] || $products !== []) {
            return;
        }

        throw ValidationException::withMessages([
            'services' => 'Add at least one service or product.',
        ]);
    }

    /**
     * Resolve an existing customer or create one from typed name/phone,
     * then ensure the customer is linked to the appointment saloon.
     */
    public function resolveCustomerId(array $payload, int $saloonId, ?int $fallbackCustomerId = null, ?User $actor = null): ?int
    {
        $customerId = $payload['customer_id'] ?? null;

        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);

            if ($customer !== null) {
                $customer->attachSaloon($saloonId);

                return $customer->id;
            }
        }

        $name = trim((string) ($payload['customer_name'] ?? ''));
        $phone = trim((string) ($payload['customer_phone'] ?? ''));

        if ($name === '' && $phone === '') {
            return $fallbackCustomerId;
        }

        $existing = null;

        if ($phone !== '') {
            $existing = Customer::query()
                ->forSaloon($saloonId)
                ->where('phone', $phone)
                ->first();

            if ($existing === null) {
                $existing = Customer::query()
                    ->where('phone', $phone)
                    ->first();
            }
        } elseif ($name !== '') {
            $existing = Customer::query()
                ->forSaloon($saloonId)
                ->where('name', $name)
                ->first();
        }

        if ($existing !== null) {
            if ($name !== '' && $existing->name !== $name) {
                $existing->update(['name' => $name]);
            }

            $email = trim((string) ($payload['customer_email'] ?? ''));
            if ($email !== '' && blank($existing->email)) {
                $existing->update(['email' => $email]);
            }

            $existing->attachSaloon($saloonId);

            return $existing->id;
        }

        if ($name === '') {
            return $fallbackCustomerId;
        }

        $email = trim((string) ($payload['customer_email'] ?? ''));

        $customer = Customer::query()->create([
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'is_active' => true,
            'created_by' => $actor?->id,
        ]);

        $customer->attachSaloon($saloonId);

        return $customer->id;
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [
            'customer',
            'staff',
            'service',
            'product',
            'branch',
            'creator',
            'services.service',
            'services.product',
            'services.staff',
            'products.product.category',
            'products.staff',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    private function defaultTypeFor(array $services): string
    {
        return $services === []
            ? Appointment::TYPE_PRODUCT_SALE
            : Appointment::TYPE_APPOINTMENT;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveBookingSource(array $payload): ?string
    {
        if (filled($payload['booking_source'] ?? null)) {
            return (string) $payload['booking_source'];
        }

        $type = $payload['type'] ?? Appointment::TYPE_APPOINTMENT;

        if ($type === Appointment::TYPE_WALK_IN) {
            return Appointment::SOURCE_WALK_IN;
        }

        if ($type === Appointment::TYPE_PRODUCT_SALE) {
            return Appointment::SOURCE_POS;
        }

        return Appointment::SOURCE_INTERNAL;
    }

    /**
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private function normalizeServices(array $services, int $saloonId, ?int $branchId = null): array
    {
        $normalized = [];

        foreach (array_values($services) as $index => $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);
            $productId = isset($row['product_id']) && $row['product_id'] !== '' && $row['product_id'] !== null
                ? (int) $row['product_id']
                : null;
            $staffId = isset($row['staff_id']) && $row['staff_id'] !== '' && $row['staff_id'] !== null
                ? (int) $row['staff_id']
                : null;

            if ($serviceId <= 0) {
                throw ValidationException::withMessages([
                    "services.{$index}.service_id" => 'Service is required.',
                ]);
            }

            $offering = SalonOfferingResolver::find($saloonId, $serviceId, $productId, $branchId);

            if ($offering === null) {
                throw ValidationException::withMessages([
                    "services.{$index}.service_id" => 'Selected service is not available for this salon.',
                ]);
            }

            $price = array_key_exists('price', $row) && $row['price'] !== null && $row['price'] !== ''
                ? (float) $row['price']
                : (float) $offering->price;

            $duration = array_key_exists('duration_minutes', $row) && $row['duration_minutes']
                ? (int) $row['duration_minutes']
                : (int) $offering->duration_minutes;

            $isStaffLocked = (bool) ($row['is_staff_locked'] ?? false);

            if ($isStaffLocked && $staffId === null) {
                throw ValidationException::withMessages([
                    "services.{$index}.staff_id" => 'Staff is required when preferred staff is selected.',
                ]);
            }

            $normalized[] = [
                'service_id' => $serviceId,
                'product_id' => $productId,
                'staff_id' => $staffId,
                'is_staff_locked' => $isStaffLocked,
                'price' => $price,
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'duration_minutes' => max(1, $duration),
                'sort_order' => $index,
                'starts_at' => $row['starts_at'] ?? null,
                'ends_at' => $row['ends_at'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * Retail lines sold at the counter or added on top of booked services.
     *
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function normalizeProducts(array $products, int $saloonId, ?int $branchId): array
    {
        $normalized = [];

        foreach (array_values($products) as $index => $row) {
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;

            if ($productId <= 0) {
                throw ValidationException::withMessages([
                    "products.{$index}.product_id" => 'Product is required.',
                ]);
            }

            if ($branchId === null) {
                throw ValidationException::withMessages([
                    "products.{$index}.product_id" => 'Select a branch before selling products.',
                ]);
            }

            $pricing = $this->retail->resolveLine(
                $saloonId,
                $branchId,
                $productId,
                $this->optionalPrice($row),
                "products.{$index}.product_id",
            );

            $quantity = max(1, (int) ($row['quantity'] ?? 1));

            $normalized[] = [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'staff_id' => isset($row['staff_id']) && $row['staff_id'] !== '' && $row['staff_id'] !== null
                    ? (int) $row['staff_id']
                    : null,
                'quantity' => $quantity,
                'unit_price' => $pricing['unit_price'],
                'unit_cost' => $pricing['unit_cost'],
                'line_total' => round($pricing['unit_price'] * $quantity, 2),
                'sort_order' => $index,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function optionalPrice(array $row): ?float
    {
        foreach (['unit_price', 'price'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (float) $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    private function servicesTotal(array $services): float
    {
        $total = 0.0;

        foreach ($services as $row) {
            $total += (float) $row['price'] * max(1, (int) ($row['quantity'] ?? 1));
        }

        return round($total, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function productsTotal(array $products): float
    {
        $total = 0.0;

        foreach ($products as $row) {
            $total += (float) $row['line_total'];
        }

        return round($total, 2);
    }

    /**
     * Keep per-line schedules when partial updates omit service times (status changes, queue actions).
     *
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private function preserveExistingLineTimes(Appointment $appointment, array $services): array
    {
        if ($services === []) {
            return $services;
        }

        $existing = $appointment->relationLoaded('services')
            ? $appointment->services
            : $appointment->services()->orderBy('sort_order')->get();

        if ($existing->isEmpty()) {
            return $services;
        }

        $bySort = $existing->keyBy('sort_order');
        $byService = $existing->keyBy('service_id');

        foreach ($services as $index => &$row) {
            if (! empty($row['starts_at']) && ! empty($row['ends_at'])) {
                continue;
            }

            $line = $bySort->get($index) ?? $byService->get((int) ($row['service_id'] ?? 0));

            if ($line === null) {
                continue;
            }

            if (empty($row['starts_at']) && $line->starts_at) {
                $row['starts_at'] = $line->starts_at->toIso8601String();
            }

            if (empty($row['ends_at']) && $line->ends_at) {
                $row['ends_at'] = $line->ends_at->toIso8601String();
            }
        }
        unset($row);

        return $services;
    }

    /**
     * Completing or cancelling a visit should not re-run booking conflicts.
     * Status-only updates with an unchanged schedule should also skip re-validation.
     *
     * @param  list<array<string, mixed>>  $timelineLines
     */
    private function shouldAssertStaffAvailability(
        Appointment $appointment,
        array $payload,
        array $timelineLines,
    ): bool {
        $nextStatus = (string) ($payload['status'] ?? $appointment->status);

        if (in_array($nextStatus, [
            AppointmentStatus::COMPLETED,
            AppointmentStatus::CANCELLED,
            AppointmentStatus::NO_SHOW,
        ], true)) {
            return false;
        }

        return $this->scheduleOrStaffChanged($appointment, $timelineLines);
    }

    /**
     * @param  list<array<string, mixed>>  $timelineLines
     */
    private function scheduleOrStaffChanged(Appointment $appointment, array $timelineLines): bool
    {
        $existing = $appointment->relationLoaded('services')
            ? $appointment->services->sortBy('sort_order')->values()
            : $appointment->services()->orderBy('sort_order')->get();

        if ($existing->count() !== count($timelineLines)) {
            return true;
        }

        foreach ($timelineLines as $index => $line) {
            $current = $existing->get($index);

            if ($current === null) {
                return true;
            }

            $staffChanged = (int) ($line['staff_id'] ?? 0) !== (int) ($current->staff_id ?? 0);

            if ($staffChanged) {
                return true;
            }

            $nextStart = Carbon::parse($line['starts_at']);
            $nextEnd = Carbon::parse($line['ends_at']);

            if ($current->starts_at === null || $current->ends_at === null) {
                return true;
            }

            if (! $nextStart->equalTo($current->starts_at) || ! $nextEnd->equalTo($current->ends_at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $services
     * @return array{starts_at: Carbon, ends_at: Carbon, lines: list<array<string, mixed>>}
     */
    private function buildTimeline(array $payload, array $services): array
    {
        $cursor = Carbon::parse($payload['starts_at']);
        $lines = [];
        $overallStart = null;
        $overallEnd = null;

        foreach ($services as $service) {
            $lineStart = ! empty($service['starts_at'])
                ? Carbon::parse($service['starts_at'])
                : $cursor->copy();

            $lineEnd = ! empty($service['ends_at'])
                ? Carbon::parse($service['ends_at'])
                : $lineStart->copy()->addMinutes((int) $service['duration_minutes']);

            if ($lineEnd->lte($lineStart)) {
                $lineEnd = $lineStart->copy()->addMinutes((int) $service['duration_minutes']);
            }

            $lines[] = [
                ...$service,
                'starts_at' => $lineStart,
                'ends_at' => $lineEnd,
                'duration_minutes' => max(1, (int) $lineStart->diffInMinutes($lineEnd)),
            ];

            $overallStart = $overallStart === null || $lineStart->lt($overallStart) ? $lineStart->copy() : $overallStart;
            $overallEnd = $overallEnd === null || $lineEnd->gt($overallEnd) ? $lineEnd->copy() : $overallEnd;

            // Sequential stacking when line times are not explicitly provided.
            if (empty($service['starts_at'])) {
                $cursor = $lineEnd->copy();
            }
        }

        // A product-only sale occupies no chair time, so it collapses to a single instant.
        return [
            'starts_at' => $overallStart ?? $cursor,
            'ends_at' => $overallEnd ?? ($services === [] ? $cursor->copy() : $cursor->copy()->addHour()),
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncServiceLines(Appointment $appointment, array $lines): void
    {
        foreach ($lines as $line) {
            $appointment->services()->create([
                'service_id' => $line['service_id'],
                'product_id' => $line['product_id'],
                'staff_id' => $line['staff_id'],
                'is_staff_locked' => (bool) ($line['is_staff_locked'] ?? false),
                'price' => $line['price'],
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'duration_minutes' => $line['duration_minutes'],
                'starts_at' => $line['starts_at'],
                'ends_at' => $line['ends_at'],
                'sort_order' => $line['sort_order'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function syncProductLines(Appointment $appointment, array $products): void
    {
        foreach ($products as $line) {
            $appointment->products()->create([
                'product_id' => $line['product_id'],
                'branch_id' => $line['branch_id'],
                'staff_id' => $line['staff_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => $line['line_total'],
                'sort_order' => $line['sort_order'],
            ]);
        }
    }

    /**
     * Move sold retail stock out of the branch once the visit is completed.
     *
     * A product on a service line is a service variant (formula / add-on),
     * not a sell quantity, so it is not deducted here.
     */
    private function maybeDeductStock(
        Appointment $appointment,
        User $actor,
        ?string $previousStatus = null,
    ): void {
        if ($appointment->status !== 'completed' || $previousStatus === 'completed') {
            return;
        }

        if ($appointment->branch_id === null) {
            return;
        }

        $alreadyDeducted = InventoryMovement::query()
            ->where('reference_type', 'appointment')
            ->where('reference_id', $appointment->id)
            ->where('type', InventoryMovementType::SALE)
            ->exists();

        if ($alreadyDeducted) {
            return;
        }

        $appointment->loadMissing(['products']);

        $lines = [];

        foreach ($appointment->products as $line) {
            $lines[] = [
                'product_id' => (int) $line->product_id,
                'quantity' => max(1, (int) $line->quantity),
            ];
        }

        $lines = $this->aggregateStockLines($lines);

        if ($lines === []) {
            return;
        }

        $branchId = (int) $appointment->branch_id;
        $this->stock->assertAvailable($branchId, $lines, 'products');
        $this->stock->deduct(
            (int) $appointment->saloon_id,
            $branchId,
            $lines,
            (int) $actor->id,
            'appointment',
            (int) $appointment->id,
        );
    }

    /**
     * Collapse repeated products so the availability check sees the true demand.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     * @return list<array{product_id: int, quantity: int}>
     */
    private function aggregateStockLines(array $lines): array
    {
        $totals = [];

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $totals[$productId] = ($totals[$productId] ?? 0) + max(1, (int) $line['quantity']);
        }

        $aggregated = [];
        foreach ($totals as $productId => $quantity) {
            $aggregated[] = ['product_id' => $productId, 'quantity' => $quantity];
        }

        return $aggregated;
    }
}
