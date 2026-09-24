<?php

namespace App\Services\Package;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageItem;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Service;
use App\Models\User;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Package\CustomerPackageStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Package
    {
        return DB::transaction(function () use ($actor, $payload): Package {
            $package = Package::query()->create([
                'saloon_id' => (int) $actor->saloon_id,
                'branch_id' => $payload['branch_id'] ?? null,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'price' => (float) $payload['price'],
                'valid_days' => $payload['valid_days'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => (int) $actor->id,
            ]);

            $this->syncItems($package, $payload['items'] ?? []);

            return $package->fresh(['items.service', 'branch', 'creator']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Package $package, array $payload): Package
    {
        return DB::transaction(function () use ($package, $payload): Package {
            $fields = [];
            foreach (['name', 'description', 'branch_id', 'valid_days'] as $key) {
                if (array_key_exists($key, $payload)) {
                    $fields[$key] = $payload[$key];
                }
            }
            if (array_key_exists('price', $payload)) {
                $fields['price'] = (float) $payload['price'];
            }
            if (array_key_exists('is_active', $payload)) {
                $fields['is_active'] = (bool) $payload['is_active'];
            }

            if ($fields !== []) {
                $package->update($fields);
            }

            if (array_key_exists('items', $payload)) {
                if ($package->customerPackages()->exists()) {
                    throw ValidationException::withMessages([
                        'items' => 'Cannot change package items after packages have been sold. Deactivate and create a new package instead.',
                    ]);
                }

                $package->items()->delete();
                $this->syncItems($package, $payload['items'] ?? []);
            }

            return $package->fresh(['items.service', 'branch', 'creator']);
        });
    }

    public function deactivate(Package $package): Package
    {
        $package->update(['is_active' => false]);

        return $package->fresh(['items.service', 'branch', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sell(User $actor, Package $package, array $payload): CustomerPackage
    {
        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => 'This package is inactive and cannot be sold.',
            ]);
        }

        $package->loadMissing('items');

        if ($package->items->isEmpty()) {
            throw ValidationException::withMessages([
                'package_id' => 'Package has no service items to sell.',
            ]);
        }

        $customer = Customer::query()->findOrFail((int) $payload['customer_id']);
        if (! $customer->belongsToSaloon((int) $package->saloon_id)) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer does not belong to this salon.',
            ]);
        }

        return DB::transaction(function () use ($actor, $package, $customer, $payload): CustomerPackage {
            $purchasedAt = now();
            $expiresAt = null;
            if ($package->valid_days !== null && (int) $package->valid_days > 0) {
                $expiresAt = $purchasedAt->copy()->addDays((int) $package->valid_days);
            }

            $customerPackage = CustomerPackage::query()->create([
                'saloon_id' => (int) $package->saloon_id,
                'branch_id' => $payload['branch_id'] ?? $package->branch_id ?? $actor->branch_id,
                'customer_id' => (int) $customer->id,
                'package_id' => (int) $package->id,
                'purchased_at' => $purchasedAt,
                'expires_at' => $expiresAt,
                'status' => CustomerPackageStatus::ACTIVE,
                'amount_paid' => (float) ($payload['amount_paid'] ?? $package->price),
                'payment_method' => $payload['payment_method'] ?? AppointmentPayment::METHOD_CASH,
                'payment_ref' => $payload['payment_ref'] ?? null,
                'sold_by' => (int) $actor->id,
                'notes' => $payload['notes'] ?? null,
            ]);

            foreach ($package->items as $item) {
                CustomerPackageItem::query()->create([
                    'customer_package_id' => $customerPackage->id,
                    'package_item_id' => $item->id,
                    'service_id' => (int) $item->service_id,
                    'quantity_total' => (int) $item->quantity_total,
                    'quantity_remaining' => (int) $item->quantity_total,
                    'unit_value' => (float) $item->unit_value,
                ]);
            }

            return $customerPackage->fresh([
                'package.items.service',
                'items.service',
                'customer',
                'seller',
                'branch',
            ]);
        });
    }

    /**
     * @return Collection<int, CustomerPackage>
     */
    public function listActiveForCustomer(int $saloonId, int $customerId): Collection
    {
        $this->expireStaleForCustomer($saloonId, $customerId);

        return CustomerPackage::query()
            ->with(['package', 'items.service', 'branch', 'seller'])
            ->where('saloon_id', $saloonId)
            ->where('customer_id', $customerId)
            ->where('status', CustomerPackageStatus::ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('purchased_at')
            ->get();
    }

    /**
     * Decrement remaining quantity for a service line on a sold package.
     *
     * @param  array<string, mixed>  $payload
     */
    public function redeem(CustomerPackage $customerPackage, array $payload): CustomerPackage
    {
        $this->refreshExpiry($customerPackage);

        if (! $customerPackage->isRedeemable()) {
            throw ValidationException::withMessages([
                'customer_package_id' => 'This package cannot be redeemed (inactive, exhausted, or expired).',
            ]);
        }

        $serviceId = (int) $payload['service_id'];
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        return DB::transaction(function () use ($customerPackage, $serviceId, $quantity): CustomerPackage {
            /** @var CustomerPackageItem|null $item */
            $item = CustomerPackageItem::query()
                ->where('customer_package_id', $customerPackage->id)
                ->where('service_id', $serviceId)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    'service_id' => 'This service is not included in the customer package.',
                ]);
            }

            if ($item->quantity_remaining < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$item->quantity_remaining} remaining for this service.",
                ]);
            }

            $item->update([
                'quantity_remaining' => $item->quantity_remaining - $quantity,
            ]);

            $remaining = CustomerPackageItem::query()
                ->where('customer_package_id', $customerPackage->id)
                ->sum('quantity_remaining');

            if ((int) $remaining <= 0) {
                $customerPackage->update(['status' => CustomerPackageStatus::EXHAUSTED]);
            }

            return $customerPackage->fresh([
                'package',
                'items.service',
                'customer',
                'seller',
                'branch',
            ]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(Package $package, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one service item.',
            ]);
        }

        $seen = [];
        foreach ($items as $index => $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);
            if ($serviceId <= 0 || isset($seen[$serviceId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.service_id" => 'Each service may only appear once in a package.',
                ]);
            }
            $seen[$serviceId] = true;

            if (! Service::query()->whereKey($serviceId)->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.service_id" => 'Service not found.',
                ]);
            }

            PackageItem::query()->create([
                'package_id' => $package->id,
                'service_id' => $serviceId,
                'quantity_total' => max(1, (int) ($row['quantity_total'] ?? 1)),
                'unit_value' => (float) ($row['unit_value'] ?? 0),
            ]);
        }
    }

    private function expireStaleForCustomer(int $saloonId, int $customerId): void
    {
        CustomerPackage::query()
            ->where('saloon_id', $saloonId)
            ->where('customer_id', $customerId)
            ->where('status', CustomerPackageStatus::ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => CustomerPackageStatus::EXPIRED]);
    }

    private function refreshExpiry(CustomerPackage $customerPackage): void
    {
        if (
            $customerPackage->status === CustomerPackageStatus::ACTIVE
            && $customerPackage->expires_at !== null
            && $customerPackage->expires_at->isPast()
        ) {
            $customerPackage->update(['status' => CustomerPackageStatus::EXPIRED]);
            $customerPackage->refresh();
        }
    }
}
