<?php

namespace App\Services\Customer;

use App\Mail\CustomerOccasionWishMail;
use App\Models\Customer;
use App\Models\Saloon;
use App\Services\Tenant\TenantNotificationDispatcher;
use App\Support\Tenant\TenantConfig;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OccasionWishService
{
    public function __construct(
        private readonly TenantConfig $tenantConfig,
        private readonly TenantNotificationDispatcher $notifications,
    ) {
    }

    /**
     * @return array{sent: int, skipped: int}
     */
    public function sendForSalon(Saloon $salon, bool $dryRun = false): array
    {
        $timezone = (string) $this->tenantConfig->get($salon, 'regional', 'timezone', config('app.timezone', 'Asia/Kolkata'));
        $today = now($timezone)->startOfDay();

        $sent = 0;
        $skipped = 0;

        foreach (['birthday', 'anniversary'] as $occasion) {
            $enabledKey = $occasion === 'birthday' ? 'birthday_wishes' : 'anniversary_wishes';
            $offerKey = $occasion === 'birthday' ? 'birthday_offer_text' : 'anniversary_offer_text';
            $sentColumn = $occasion === 'birthday' ? 'last_birthday_wish_on' : 'last_anniversary_wish_on';
            $dateColumn = $occasion;

            if (! $this->tenantConfig->notificationEnabled($salon, $enabledKey, true)) {
                continue;
            }

            $offerText = trim((string) $this->tenantConfig->get($salon, 'notifications', $offerKey, ''));

            $customers = Customer::query()
                ->forSaloon((int) $salon->id)
                ->where('is_active', true)
                ->whereNotNull('email')
                ->whereNotNull($dateColumn)
                ->where($this->monthDayMatcher($dateColumn, $today))
                ->where(function (Builder $query) use ($sentColumn, $today): void {
                    $query->whereNull($sentColumn)
                        ->orWhereYear($sentColumn, '<', $today->year);
                })
                ->orderBy('id')
                ->get();

            foreach ($customers as $customer) {
                $email = trim((string) $customer->email);
                if ($email === '') {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $sent++;

                    continue;
                }

                $delivered = $this->notifications->sendMail(
                    $salon,
                    $email,
                    new CustomerOccasionWishMail($salon, $customer, $occasion, $offerText),
                    $enabledKey,
                    true,
                );

                if ($delivered) {
                    $customer->forceFill([$sentColumn => $today->toDateString()])->save();
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * @return \Closure(Builder<Customer>): void
     */
    private function monthDayMatcher(string $column, Carbon $today): \Closure
    {
        $month = (int) $today->month;
        $day = (int) $today->day;
        $includeLeapDay = $month === 2 && $day === 28 && ! $today->isLeapYear();

        return function (Builder $query) use ($column, $month, $day, $includeLeapDay): void {
            $query->where(function (Builder $inner) use ($column, $month, $day, $includeLeapDay): void {
                $inner->whereMonth($column, $month)->whereDay($column, $day);

                if ($includeLeapDay) {
                    $inner->orWhere(function (Builder $leap) use ($column): void {
                        $leap->whereMonth($column, 2)->whereDay($column, 29);
                    });
                }
            });
        };
    }
}
