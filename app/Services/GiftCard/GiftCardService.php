<?php

namespace App\Services\GiftCard;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\User;
use App\Support\GiftCard\GiftCardStatus;
use App\Support\GiftCard\GiftCardTransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardService
{
    /**
     * Issue a new gift card (sell).
     *
     * @param  array<string, mixed>  $payload
     */
    public function issue(User $actor, array $payload): GiftCard
    {
        $saloonId = (int) $actor->saloon_id;
        $balance = round((float) $payload['initial_balance'], 2);

        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'initial_balance' => 'Initial balance must be greater than zero.',
            ]);
        }

        if (! empty($payload['purchaser_customer_id'])) {
            $customer = Customer::query()->findOrFail((int) $payload['purchaser_customer_id']);
            if (! $customer->belongsToSaloon($saloonId)) {
                throw ValidationException::withMessages([
                    'purchaser_customer_id' => 'Customer does not belong to this salon.',
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $payload, $saloonId, $balance): GiftCard {
            $code = $this->normalizeCode($payload['code'] ?? null) ?? $this->generateUniqueCode($saloonId);

            if (GiftCard::query()->where('saloon_id', $saloonId)->where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'A gift card with this code already exists.',
                ]);
            }

            $giftCard = GiftCard::query()->create([
                'saloon_id' => $saloonId,
                'branch_id' => $payload['branch_id'] ?? $actor->branch_id,
                'code' => $code,
                'initial_balance' => $balance,
                'current_balance' => $balance,
                'currency' => $payload['currency'] ?? 'INR',
                'status' => GiftCardStatus::ACTIVE,
                'purchaser_customer_id' => $payload['purchaser_customer_id'] ?? null,
                'recipient_name' => $payload['recipient_name'] ?? null,
                'recipient_phone' => $payload['recipient_phone'] ?? null,
                'issued_at' => now(),
                'expires_at' => $payload['expires_at'] ?? null,
                'sold_appointment_id' => $payload['sold_appointment_id'] ?? null,
                'issued_by' => (int) $actor->id,
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->writeLedger(
                $giftCard,
                GiftCardTransactionType::ISSUE,
                $balance,
                $balance,
                (int) $actor->id,
                $payload['reason'] ?? 'Gift card issued',
                $payload['sold_appointment_id'] ?? null,
            );

            return $giftCard->fresh(['purchaser', 'issuer', 'branch', 'transactions']);
        });
    }

    public function lookup(int $saloonId, string $code): ?GiftCard
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            return null;
        }

        $giftCard = GiftCard::query()
            ->with(['purchaser', 'issuer', 'branch', 'transactions' => fn ($q) => $q->latest('id')->limit(20)])
            ->where('saloon_id', $saloonId)
            ->where('code', $normalized)
            ->first();

        if ($giftCard !== null) {
            $this->refreshExpiry($giftCard);
            $giftCard->refresh();
            $giftCard->load(['purchaser', 'issuer', 'branch', 'transactions' => fn ($q) => $q->latest('id')->limit(20)]);
        }

        return $giftCard;
    }

    /**
     * Reduce balance (POS / desk redeem). Amount is the spend amount (positive).
     *
     * @param  array<string, mixed>  $payload
     */
    public function redeem(User $actor, GiftCard $giftCard, array $payload): GiftCard
    {
        $this->refreshExpiry($giftCard);

        if (! $giftCard->isRedeemable()) {
            throw ValidationException::withMessages([
                'gift_card_id' => 'This gift card cannot be redeemed.',
            ]);
        }

        $amount = round((float) $payload['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Redeem amount must be greater than zero.',
            ]);
        }

        if ($amount > (float) $giftCard->current_balance) {
            throw ValidationException::withMessages([
                'amount' => 'Redeem amount exceeds remaining balance.',
            ]);
        }

        return DB::transaction(function () use ($actor, $giftCard, $payload, $amount): GiftCard {
            /** @var GiftCard $locked */
            $locked = GiftCard::query()->whereKey($giftCard->id)->lockForUpdate()->firstOrFail();
            $this->refreshExpiry($locked);

            if (! $locked->isRedeemable() || $amount > (float) $locked->current_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Gift card balance changed; try again.',
                ]);
            }

            $balanceAfter = round((float) $locked->current_balance - $amount, 2);
            $status = $balanceAfter <= 0 ? GiftCardStatus::REDEEMED : GiftCardStatus::ACTIVE;

            $locked->update([
                'current_balance' => max($balanceAfter, 0),
                'status' => $status,
            ]);

            $this->writeLedger(
                $locked,
                GiftCardTransactionType::REDEEM,
                -1 * $amount,
                max($balanceAfter, 0),
                (int) $actor->id,
                $payload['reason'] ?? 'Redeemed',
                $payload['appointment_id'] ?? null,
            );

            return $locked->fresh(['purchaser', 'issuer', 'branch', 'transactions']);
        });
    }

    /**
     * Manual balance adjustment (positive = credit, negative = debit). Requires reason.
     *
     * @param  array<string, mixed>  $payload
     */
    public function adjust(User $actor, GiftCard $giftCard, array $payload): GiftCard
    {
        $amount = round((float) $payload['amount'], 2);
        if ($amount == 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount cannot be zero.',
            ]);
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for gift card adjustments.',
            ]);
        }

        return DB::transaction(function () use ($actor, $giftCard, $payload, $amount, $reason): GiftCard {
            /** @var GiftCard $locked */
            $locked = GiftCard::query()->whereKey($giftCard->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [GiftCardStatus::DISABLED, GiftCardStatus::EXPIRED], true)
                && $amount < 0) {
                throw ValidationException::withMessages([
                    'status' => 'Cannot debit a disabled or expired gift card.',
                ]);
            }

            $balanceAfter = round((float) $locked->current_balance + $amount, 2);
            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment would make the balance negative.',
                ]);
            }

            $status = $locked->status;
            if ($status === GiftCardStatus::DISABLED) {
                // keep disabled unless explicitly reactivated via status payload
            } elseif ($balanceAfter <= 0) {
                $status = GiftCardStatus::REDEEMED;
            } elseif (in_array($status, [GiftCardStatus::REDEEMED, GiftCardStatus::ACTIVE], true)) {
                $status = GiftCardStatus::ACTIVE;
            }

            if (! empty($payload['status']) && in_array($payload['status'], GiftCardStatus::ALL, true)) {
                $status = $payload['status'];
            }

            $locked->update([
                'current_balance' => $balanceAfter,
                'status' => $status,
            ]);

            $this->writeLedger(
                $locked,
                GiftCardTransactionType::ADJUST,
                $amount,
                $balanceAfter,
                (int) $actor->id,
                $reason,
                $payload['appointment_id'] ?? null,
            );

            return $locked->fresh(['purchaser', 'issuer', 'branch', 'transactions']);
        });
    }

    private function writeLedger(
        GiftCard $giftCard,
        string $type,
        float $amount,
        float $balanceAfter,
        int $createdBy,
        ?string $reason,
        ?int $appointmentId,
    ): void {
        GiftCardTransaction::query()->create([
            'gift_card_id' => $giftCard->id,
            'saloon_id' => (int) $giftCard->saloon_id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'appointment_id' => $appointmentId,
            'created_by' => $createdBy,
            'reason' => $reason,
        ]);
    }

    private function generateUniqueCode(int $saloonId): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = 'GC-'.Str::upper(Str::random(8));
            if (! GiftCard::query()->where('saloon_id', $saloonId)->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'code' => 'Unable to generate a unique gift card code.',
        ]);
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = Str::upper(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    private function refreshExpiry(GiftCard $giftCard): void
    {
        if (
            $giftCard->status === GiftCardStatus::ACTIVE
            && $giftCard->expires_at !== null
            && $giftCard->expires_at->isPast()
        ) {
            $giftCard->update(['status' => GiftCardStatus::EXPIRED]);
            $giftCard->refresh();
        }
    }
}
