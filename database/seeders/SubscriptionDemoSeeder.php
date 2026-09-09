<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Service;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SaloonSubscription;
use App\Models\SaloonSubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo salons for manual subscription lifecycle testing.
 *
 * Password for all owners below: Demo@123
 *
 * Case 1 — Free trial expired (portal locked, upgrade required):
 *   Email: free.expired@demo.test
 *   Salon: Free Trial Expired Demo
 *   Expected: redirect to /subscription-expired, billing only
 *
 * Case 2 — Paid plan expired (view-only, renew to edit):
 *   Email: paid.expired@demo.test
 *   Salon: Paid Plan Expired Demo
 *   Expected: browse data, no create/edit/delete, renew via /billing
 *
 * Case 3 — Free trial active (15 days remaining):
 *   Email: free.active@demo.test
 *   Salon: Free Trial Active Demo
 *   Expected: full free-plan access, trial banner in header
 *
 * Case 4 — Paid plan expiring soon (7 days — renewal reminder window):
 *   Email: paid.expiring@demo.test
 *   Salon: Paid Plan Expiring Demo
 *   Expected: full access; eligible for subscriptions:send-renewal-reminders
 */
class SubscriptionDemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo@123';

    public const FREE_EXPIRED_EMAIL = 'free.expired@demo.test';

    public const PAID_EXPIRED_EMAIL = 'paid.expired@demo.test';

    public const FREE_ACTIVE_EMAIL = 'free.active@demo.test';

    public const PAID_EXPIRING_EMAIL = 'paid.expiring@demo.test';

    public function run(): void
    {
        $entitlements = app(SubscriptionEntitlements::class);

        $freePlan = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $this->seedFreeExpiredSalon($entitlements, $freePlan);
        $this->seedPaidExpiredSalon($entitlements, $basicPlan);
        $this->seedFreeActiveSalon($entitlements, $freePlan);
        $this->seedPaidExpiringSalon($entitlements, $basicPlan);

        if ($this->command !== null) {
            $this->command->newLine();
            $this->command->info('Subscription demo salons seeded (password: '.self::PASSWORD.'):');
            $this->command->table(
                ['Case', 'Email', 'Salon', 'Expected access'],
                [
                    [
                        '1 Free expired',
                        self::FREE_EXPIRED_EMAIL,
                        'Free Trial Expired Demo',
                        'Locked → /subscription-expired',
                    ],
                    [
                        '2 Paid expired',
                        self::PAID_EXPIRED_EMAIL,
                        'Paid Plan Expired Demo',
                        'Read-only → view data, renew on /billing',
                    ],
                    [
                        '3 Free active',
                        self::FREE_ACTIVE_EMAIL,
                        'Free Trial Active Demo',
                        'Full access — 15 days trial left',
                    ],
                    [
                        '4 Paid expiring',
                        self::PAID_EXPIRING_EMAIL,
                        'Paid Plan Expiring Demo',
                        'Full access — renews in 7 days (reminder cron)',
                    ],
                ],
            );
        }
    }

    private function seedFreeActiveSalon(SubscriptionEntitlements $entitlements, SubscriptionPlan $freePlan): void
    {
        $salon = Saloon::query()->updateOrCreate(
            ['name' => 'Free Trial Active Demo'],
            [
                'branch_name' => 'Central',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'phone' => '+919800000401',
                'is_active' => true,
                'activation_status' => Saloon::ACTIVATION_ACTIVE,
            ],
        );

        $branch = SaloonBranch::query()->updateOrCreate(
            ['saloon_id' => $salon->id, 'branch_name' => 'Central'],
            [
                'business_address_1' => '22 Trial Lane',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'area_pincode' => '110001',
                'country' => 'India',
                'is_active' => true,
            ],
        );

        $owner = $this->upsertOwner(self::FREE_ACTIVE_EMAIL, 'Free Active Owner', '+919800000402', $salon);

        $this->resetSalonSubscriptions($salon->fresh());

        $trialEndsAt = now()->addDays(15)->startOfDay()->addHours(12);
        $subscription = $entitlements->assignPlan(
            $salon->fresh(),
            $freePlan,
            startTrial: true,
            trialDays: 15,
        );

        $subscription->update([
            'status' => SaloonSubscription::STATUS_TRIALING,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $trialEndsAt,
        ]);

        $this->seedSampleOperationalData(
            $salon->fresh(),
            $branch,
            $owner,
            customerPhone: '+919800000403',
            customerEmail: 'customer.free.active@demo.test',
            appointmentNotes: 'Demo appointment — active free trial',
        );
    }

    private function seedPaidExpiringSalon(SubscriptionEntitlements $entitlements, SubscriptionPlan $basicPlan): void
    {
        $salon = Saloon::query()->updateOrCreate(
            ['name' => 'Paid Plan Expiring Demo'],
            [
                'branch_name' => 'Waterfront',
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'phone' => '+919800000501',
                'is_active' => true,
                'activation_status' => Saloon::ACTIVATION_ACTIVE,
            ],
        );

        $branch = SaloonBranch::query()->updateOrCreate(
            ['saloon_id' => $salon->id, 'branch_name' => 'Waterfront'],
            [
                'business_address_1' => '8 Marina View',
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'area_pincode' => '600001',
                'country' => 'India',
                'is_active' => true,
            ],
        );

        $owner = $this->upsertOwner(self::PAID_EXPIRING_EMAIL, 'Paid Expiring Owner', '+919800000502', $salon);

        $this->resetSalonSubscriptions($salon->fresh());

        $endsAt = now()->addDays(7)->startOfDay()->addHours(12);
        $startsAt = $endsAt->copy()->subMonth();
        $subscription = $entitlements->assignPlan($salon->fresh(), $basicPlan, startTrial: false);

        $subscription->update([
            'status' => SaloonSubscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => null,
            'renewal_reminder_sent_at' => null,
            'renewal_reminder_days_sent' => null,
        ]);

        $this->seedSampleOperationalData(
            $salon->fresh(),
            $branch,
            $owner,
            customerPhone: '+919800000503',
            customerEmail: 'customer.paid.expiring@demo.test',
            appointmentNotes: 'Demo appointment — paid plan expiring in 7 days',
        );
    }

    /**
     * Clear subscription rows so re-seeding demo salons does not leave stray active free trials.
     */
    private function resetSalonSubscriptions(Saloon $salon): void
    {
        SaloonSubscriptionHistory::query()->where('saloon_id', $salon->id)->delete();
        SaloonSubscription::query()->where('saloon_id', $salon->id)->delete();
    }

    private function upsertOwner(string $email, string $name, string $phone, Saloon $salon): User
    {
        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER)
            ?? throw new \RuntimeException('Missing salon owner role.');

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make(self::PASSWORD),
                'role_id' => $ownerRole->id,
                'saloon_id' => $salon->id,
                'branch_id' => null,
                'onboarding_completed_at' => now(),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
    }

    private function seedSampleOperationalData(
        Saloon $salon,
        SaloonBranch $branch,
        User $owner,
        string $customerPhone,
        string $customerEmail,
        string $appointmentNotes,
    ): void {
        $service = Service::query()->where('is_active', true)->orderBy('id')->first();

        $customer = Customer::query()->updateOrCreate(
            ['phone' => $customerPhone],
            [
                'name' => 'Demo Customer',
                'email' => $customerEmail,
                'notes' => 'Sample customer for subscription demo',
                'is_active' => true,
            ],
        );
        $customer->attachSaloon((int) $salon->id);

        if ($service === null) {
            return;
        }

        SalonServiceProduct::query()->updateOrCreate(
            [
                'saloon_id' => $salon->id,
                'branch_id' => $branch->id,
                'service_id' => $service->id,
            ],
            [
                'product_id' => null,
                'price' => $service->default_price ?? 599,
                'duration_minutes' => $service->duration_minutes ?? 45,
                'is_active' => true,
            ],
        );

        $startsAt = now()->addDay()->setTime(15, 0);
        Appointment::query()->updateOrCreate(
            [
                'saloon_id' => $salon->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'starts_at' => $startsAt,
            ],
            [
                'staff_id' => $owner->id,
                'service_id' => $service->id,
                'ends_at' => $startsAt->copy()->addMinutes((int) ($service->duration_minutes ?? 45)),
                'status' => 'scheduled',
                'price' => $service->default_price ?? 599,
                'notes' => $appointmentNotes,
            ],
        );
    }

    private function seedFreeExpiredSalon(SubscriptionEntitlements $entitlements, SubscriptionPlan $freePlan): void
    {
        $salon = Saloon::query()->updateOrCreate(
            ['name' => 'Free Trial Expired Demo'],
            [
                'branch_name' => 'Main Branch',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'phone' => '+919800000101',
                'is_active' => true,
                'activation_status' => Saloon::ACTIVATION_ACTIVE,
            ],
        );

        $branch = SaloonBranch::query()->updateOrCreate(
            ['saloon_id' => $salon->id, 'branch_name' => 'Main Branch'],
            [
                'business_address_1' => '101 Demo Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400001',
                'country' => 'India',
                'is_active' => true,
            ],
        );

        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER)
            ?? throw new \RuntimeException('Missing salon owner role.');

        User::query()->updateOrCreate(
            ['email' => self::FREE_EXPIRED_EMAIL],
            [
                'name' => 'Free Expired Owner',
                'phone' => '+919800000102',
                'password' => Hash::make(self::PASSWORD),
                'role_id' => $ownerRole->id,
                'saloon_id' => $salon->id,
                'branch_id' => null,
                'onboarding_completed_at' => now(),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $this->resetSalonSubscriptions($salon->fresh());

        $expiredAt = now()->subDays(7)->startOfDay()->addHours(12);
        $startsAt = $expiredAt->copy()->subDays(30);
        $subscription = $entitlements->assignPlan($salon->fresh(), $freePlan, startTrial: true);

        $subscription->update([
            'status' => SaloonSubscription::STATUS_EXPIRED,
            'starts_at' => $startsAt,
            'trial_ends_at' => $expiredAt,
            'ends_at' => $expiredAt,
        ]);

        $entitlements->recordHistory(
            saloon: $salon->fresh(),
            subscription: $subscription->fresh(),
            planId: (int) $freePlan->id,
            fromPlanId: null,
            action: SaloonSubscriptionHistory::ACTION_EXPIRED,
            notes: 'Demo: free trial expired (Case 1 — locked portal).',
        );
    }

    private function seedPaidExpiredSalon(SubscriptionEntitlements $entitlements, SubscriptionPlan $basicPlan): void
    {
        $salon = Saloon::query()->updateOrCreate(
            ['name' => 'Paid Plan Expired Demo'],
            [
                'branch_name' => 'Downtown',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'phone' => '+919800000201',
                'is_active' => true,
                'activation_status' => Saloon::ACTIVATION_ACTIVE,
            ],
        );

        $branch = SaloonBranch::query()->updateOrCreate(
            ['saloon_id' => $salon->id, 'branch_name' => 'Downtown'],
            [
                'business_address_1' => '55 Renewal Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'area_pincode' => '411001',
                'country' => 'India',
                'is_active' => true,
            ],
        );

        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER)
            ?? throw new \RuntimeException('Missing salon owner role.');
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);

        $owner = User::query()->updateOrCreate(
            ['email' => self::PAID_EXPIRED_EMAIL],
            [
                'name' => 'Paid Expired Owner',
                'phone' => '+919800000202',
                'password' => Hash::make(self::PASSWORD),
                'role_id' => $ownerRole->id,
                'saloon_id' => $salon->id,
                'branch_id' => null,
                'onboarding_completed_at' => now(),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if ($staffRole !== null) {
            User::query()->updateOrCreate(
                ['email' => 'staff.paid.expired@demo.test'],
                [
                    'name' => 'Demo Stylist',
                    'phone' => '+919800000203',
                    'password' => Hash::make(self::PASSWORD),
                    'role_id' => $staffRole->id,
                    'saloon_id' => $salon->id,
                    'branch_id' => $branch->id,
                    'onboarding_completed_at' => now(),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
        }

        $this->resetSalonSubscriptions($salon->fresh());

        $expiredAt = now()->subDays(3)->startOfDay()->addHours(12);
        $startsAt = $expiredAt->copy()->subMonth();
        $subscription = $entitlements->assignPlan($salon->fresh(), $basicPlan, startTrial: false);

        $subscription->update([
            'status' => SaloonSubscription::STATUS_EXPIRED,
            'starts_at' => $startsAt,
            'ends_at' => $expiredAt,
            'trial_ends_at' => null,
        ]);

        $entitlements->recordHistory(
            saloon: $salon->fresh(),
            subscription: $subscription->fresh(),
            planId: (int) $basicPlan->id,
            fromPlanId: null,
            action: SaloonSubscriptionHistory::ACTION_EXPIRED,
            notes: 'Demo: paid subscription expired (Case 2 — read-only).',
        );

        $this->seedSampleOperationalData(
            $salon->fresh(),
            $branch,
            $owner->fresh(),
            customerPhone: '+919800000301',
            customerEmail: 'customer.paid.expired@demo.test',
            appointmentNotes: 'Demo appointment — view-only salon',
        );
    }
}
