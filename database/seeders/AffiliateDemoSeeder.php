<?php

namespace Database\Seeders;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePartner;
use App\Models\AffiliateReferral;
use App\Models\AffiliateWithdrawalRequest;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Database\WriteRetry;
use App\Support\Role\RoleCodes;
use App\Support\Saloon\ReferralCodeGenerator;
use App\Support\Staff\DefaultStaffProvisioner;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds dynamic affiliate partner accounts with referrals, commissions, and withdrawals.
 *
 * Re-runnable: previous rows tagged by this seeder are purged first.
 * Partner login password: DemoDataSeeder::DEMO_PASSWORD (Demo@123)
 * Generated emails are printed to the console when seeded via artisan.
 */
class AffiliateDemoSeeder extends Seeder
{
    public const SEED_MARKER = 'seeded_by:AffiliateDemoSeeder';

    public const PARTNER_COUNT = 3;

    public function run(): void
    {
        if (! Schema::hasTable('affiliate_partners')) {
            return;
        }

        $role = Role::findByCode(RoleCodes::AFFILIATE_PARTNER);
        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

        if ($role === null || $ownerRole === null) {
            return;
        }

        $this->purgePreviousDemoData();

        $created = [];

        for ($index = 1; $index <= self::PARTNER_COUNT; $index++) {
            $created[] = WriteRetry::run(
                fn () => $this->seedPartnerBundle($role, $ownerRole, $index),
                attempts: 6,
                sleepMs: 100,
            );
        }

        $this->printCredentials($created);
    }

    /**
     * @return array{email: string, code: string, display_name: string, referrals: int, commissions: int}
     */
    private function seedPartnerBundle(Role $affiliateRole, Role $ownerRole, int $index): array
    {
        $faker = fake();

        $firstName = $faker->firstName();
        $lastName = $faker->lastName();
        $company = $faker->unique()->company();
        $slug = Str::lower(Str::slug(Str::limit($company, 18, ''), ''));
        $slug = $slug !== '' ? $slug : 'partner'.$index;
        $email = sprintf('affiliate.%s.%s@demo.test', $slug, Str::lower(Str::random(4)));
        $code = $this->uniqueAffiliateCode($company, $index);

        $onboardingRate = $faker->randomFloat(2, 10, 15);
        $renewalRate = $faker->randomFloat(2, 3, 6);
        $lockDays = $faker->randomElement([15, 30, 45, 60]);
        $joinedAt = now()->subDays($faker->numberBetween(40, 180));

        $user = WriteRetry::run(fn () => User::query()->create([
            'name' => trim($firstName.' '.$lastName),
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $email,
            'phone' => $faker->unique()->numerify('+919#########'),
            'password' => DemoDataSeeder::DEMO_PASSWORD,
            'role_id' => $affiliateRole->id,
            'saloon_id' => null,
            'is_active' => true,
            'onboarding_completed_at' => $joinedAt,
            'email_verified_at' => $joinedAt,
        ]));

        $partner = WriteRetry::run(fn () => AffiliatePartner::query()->create([
            'user_id' => $user->id,
            'code' => $code,
            'display_name' => $company,
            'status' => AffiliatePartner::STATUS_ACTIVE,
            'onboarding_commission_rate' => $onboardingRate,
            'renewal_commission_rate' => $renewalRate,
            'commission_lock_days' => $lockDays,
            'payout_method' => $faker->randomElement(['bank_transfer', 'upi', 'paypal']),
            'payout_details' => [
                'account_holder' => $user->name,
                'bank_name' => $faker->company().' Bank',
                'account_last4' => $faker->numerify('####'),
                'upi' => Str::lower(Str::slug($firstName)).'@'.$faker->randomElement(['oksbi', 'paytm', 'ybl']),
            ],
            'notes' => self::SEED_MARKER,
            'joined_at' => $joinedAt,
            'activated_at' => $joinedAt->copy()->addDays($faker->numberBetween(0, 5)),
        ]));

        $referralCount = $faker->numberBetween(2, 4);
        $commissionCount = 0;

        $availableCommissions = collect();

        for ($r = 0; $r < $referralCount; $r++) {
            $bundle = $this->seedReferredSalon(
                partner: $partner,
                ownerRole: $ownerRole,
                onboardingRate: (float) $onboardingRate,
                renewalRate: (float) $renewalRate,
                lockDays: (int) $lockDays,
            );

            $commissionCount += $bundle['commission_count'];
            $availableCommissions = $availableCommissions->merge($bundle['available_commissions']);
        }

        if ($availableCommissions->isNotEmpty() && $faker->boolean(70)) {
            $this->seedWithdrawalForCommissions($partner, $availableCommissions);
        }

        return [
            'email' => $email,
            'code' => $code,
            'display_name' => $company,
            'referrals' => $referralCount,
            'commissions' => $commissionCount,
        ];
    }

    /**
     * @return array{commission_count: int, available_commissions: \Illuminate\Support\Collection<int, AffiliateCommission>}
     */
    private function seedReferredSalon(
        AffiliatePartner $partner,
        Role $ownerRole,
        float $onboardingRate,
        float $renewalRate,
        int $lockDays,
    ): array {
        $faker = fake();
        $referredAt = now()->subDays($faker->numberBetween(5, max(6, (int) $partner->joined_at?->diffInDays(now()) ?: 60)));
        $salonName = $faker->unique()->company().' '.$faker->randomElement(['Salon', 'Studio', 'Spa', 'Lounge']);
        $paymentAmount = $faker->randomFloat(2, 999, 24999);

        $saloon = Saloon::query()->create([
            'name' => $salonName,
            'city' => $faker->city(),
            'state' => $faker->state(),
            'phone' => $faker->unique()->numerify('+918#########'),
            'payment_type' => $faker->randomElement(['Monthly', 'Quarterly', 'Yearly', 'One-time']),
            'payment_amount' => $paymentAmount,
            'transaction_id' => 'AFF-'.Str::upper(Str::random(10)),
            'affiliate_partner_id' => $partner->id,
            'affiliate_referral_code' => $partner->code,
            'affiliate_attributed_at' => $referredAt,
            'is_active' => true,
            'referral_code' => ReferralCodeGenerator::generate($salonName),
        ]);

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => $faker->randomElement(['Main', 'Downtown', 'Central', 'Flagship']).' Branch',
            'business_address_1' => $faker->streetAddress(),
            'business_address_2' => $faker->optional(0.4)->secondaryAddress(),
            'city' => $saloon->city,
            'state' => $saloon->state,
            'area_pincode' => $faker->numerify('######'),
            'country' => 'India',
            'is_active' => true,
        ]);

        $ownerFirst = $faker->firstName();
        $ownerLast = $faker->lastName();

        $owner = User::query()->create([
            'name' => trim($ownerFirst.' '.$ownerLast),
            'firstname' => $ownerFirst,
            'lastname' => $ownerLast,
            'email' => $faker->unique()->safeEmail(),
            'phone' => $faker->unique()->numerify('+917#########'),
            'password' => DemoDataSeeder::DEMO_PASSWORD,
            'role_id' => $ownerRole->id,
            'saloon_id' => $saloon->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'onboarding_completed_at' => $referredAt,
            'email_verified_at' => $referredAt,
            'notes' => self::SEED_MARKER,
        ]);

        $plan = \App\Models\SubscriptionPlan::query()->where('slug', 'pro')->first()
            ?? \App\Models\SubscriptionPlan::query()->where('is_public', true)->orderByDesc('price')->first()
            ?? \App\Models\SubscriptionPlan::query()->where('slug', 'free')->first();

        if ($plan !== null) {
            app(SubscriptionEntitlements::class)->assignPlan($saloon, $plan, startTrial: false);
        }

        app(DefaultStaffProvisioner::class)->ensureDefaultStaff(
            $saloon,
            $branch,
            password: DemoDataSeeder::DEMO_PASSWORD,
        );

        $source = $faker->randomElement([
            AffiliateReferral::SOURCE_LINK,
            AffiliateReferral::SOURCE_LINK,
            AffiliateReferral::SOURCE_ADMIN,
            AffiliateReferral::SOURCE_DIRECT,
        ]);

        $referral = AffiliateReferral::query()->create([
            'affiliate_partner_id' => $partner->id,
            'saloon_id' => $saloon->id,
            'owner_user_id' => $owner->id,
            'referral_code' => $partner->code,
            'source' => $source,
            'metadata' => [
                'channel' => $source === AffiliateReferral::SOURCE_ADMIN ? 'admin_onboarding' : 'signup_link',
                'seed' => self::SEED_MARKER,
            ],
            'referred_at' => $referredAt,
            'converted_at' => $referredAt->copy()->addHours($faker->numberBetween(1, 48)),
        ]);

        $available = collect();
        $commissionCount = 0;

        $onboarding = $this->createCommission(
            partner: $partner,
            referral: $referral,
            saloon: $saloon,
            type: AffiliateCommission::TYPE_ONBOARDING,
            rate: $onboardingRate,
            baseAmount: $paymentAmount,
            lockDays: $lockDays,
            createdAt: $referredAt,
        );
        $commissionCount++;
        if ($onboarding->status === AffiliateCommission::STATUS_AVAILABLE) {
            $available->push($onboarding);
        }

        if ($faker->boolean(55)) {
            $renewalBase = $faker->randomFloat(2, max(499, $paymentAmount * 0.35), $paymentAmount);
            $renewalAt = $referredAt->copy()->addDays($faker->numberBetween(20, 90));
            if ($renewalAt->greaterThan(now())) {
                $renewalAt = now()->subDays($faker->numberBetween(1, 12));
            }

            $renewal = $this->createCommission(
                partner: $partner,
                referral: $referral,
                saloon: $saloon,
                type: AffiliateCommission::TYPE_RENEWAL,
                rate: $renewalRate,
                baseAmount: $renewalBase,
                lockDays: $lockDays,
                createdAt: $renewalAt,
            );
            $commissionCount++;
            if ($renewal->status === AffiliateCommission::STATUS_AVAILABLE) {
                $available->push($renewal);
            }
        }

        return [
            'commission_count' => $commissionCount,
            'available_commissions' => $available,
        ];
    }

    private function createCommission(
        AffiliatePartner $partner,
        AffiliateReferral $referral,
        Saloon $saloon,
        string $type,
        float $rate,
        float $baseAmount,
        int $lockDays,
        $createdAt,
    ): AffiliateCommission {
        $commissionAmount = round(max(0, $baseAmount) * ($rate / 100), 2);
        $lockedUntil = $createdAt->copy()->addDays(max(0, $lockDays));
        $matured = $lockedUntil->lessThanOrEqualTo(now());

        $status = $matured
            ? AffiliateCommission::STATUS_AVAILABLE
            : AffiliateCommission::STATUS_LOCKED;

        $commission = AffiliateCommission::query()->create([
            'affiliate_partner_id' => $partner->id,
            'affiliate_referral_id' => $referral->id,
            'saloon_id' => $saloon->id,
            'type' => $type,
            'status' => $status,
            'currency' => 'INR',
            'base_amount' => $baseAmount,
            'commission_rate' => $rate,
            'commission_amount' => $commissionAmount,
            'locked_until' => $lockedUntil,
            'available_at' => $matured ? $lockedUntil : null,
            'released_at' => $matured ? $lockedUntil : null,
            'notes' => self::SEED_MARKER.' · '.$type.' commission',
            'meta' => ['seed' => self::SEED_MARKER],
        ]);

        $commission->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $commission->fresh();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AffiliateCommission>  $availableCommissions
     */
    private function seedWithdrawalForCommissions(
        AffiliatePartner $partner,
        $availableCommissions,
    ): void {
        $faker = fake();
        $selected = $availableCommissions
            ->shuffle()
            ->take($faker->numberBetween(1, min(3, $availableCommissions->count())))
            ->values();

        if ($selected->isEmpty()) {
            return;
        }

        $amount = round((float) $selected->sum(fn (AffiliateCommission $row) => (float) $row->commission_amount), 2);
        $requestedAt = now()->subDays($faker->numberBetween(1, 20));
        $markPaid = $faker->boolean(50);

        $withdrawal = AffiliateWithdrawalRequest::query()->create([
            'affiliate_partner_id' => $partner->id,
            'amount' => $amount,
            'currency' => 'INR',
            'status' => $markPaid
                ? AffiliateWithdrawalRequest::STATUS_PAID
                : $faker->randomElement([
                    AffiliateWithdrawalRequest::STATUS_PENDING,
                    AffiliateWithdrawalRequest::STATUS_APPROVED,
                ]),
            'payout_reference' => $markPaid ? 'NEFT-'.Str::upper(Str::random(8)) : null,
            'notes' => self::SEED_MARKER,
            'requested_at' => $requestedAt,
            'reviewed_at' => $markPaid || $faker->boolean(40) ? $requestedAt->copy()->addDays(1) : null,
            'paid_at' => $markPaid ? $requestedAt->copy()->addDays(2) : null,
        ]);

        foreach ($selected as $commission) {
            $commission->update([
                'affiliate_withdrawal_request_id' => $withdrawal->id,
                'status' => $markPaid
                    ? AffiliateCommission::STATUS_PAID
                    : AffiliateCommission::STATUS_REQUESTED,
                'withdrawn_at' => $markPaid ? $withdrawal->paid_at : $requestedAt,
            ]);
        }
    }

    private function uniqueAffiliateCode(string $company, int $index): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $company) ?: 'AFF', 0, 6));
        $suffix = Str::upper(Str::random(4));
        $code = 'AFF-'.$base.$suffix;

        $attempts = 0;
        while (AffiliatePartner::query()->where('code', $code)->exists() && $attempts < 10) {
            $code = 'AFF-'.$base.Str::upper(Str::random(4)).$index;
            $attempts++;
        }

        return $code;
    }

    private function purgePreviousDemoData(): void
    {
        $this->purgeLegacyStaticAffiliate();

        $partners = AffiliatePartner::query()
            ->where('notes', self::SEED_MARKER)
            ->with('user')
            ->get();

        if ($partners->isEmpty()) {
            return;
        }

        $partnerIds = $partners->pluck('id');
        $userIds = $partners->pluck('user_id')->filter()->all();

        $saloonIds = Saloon::query()
            ->whereIn('affiliate_partner_id', $partnerIds)
            ->pluck('id');

        AffiliateCommission::query()->whereIn('affiliate_partner_id', $partnerIds)->delete();
        AffiliateWithdrawalRequest::query()->whereIn('affiliate_partner_id', $partnerIds)->delete();
        AffiliateReferral::query()->whereIn('affiliate_partner_id', $partnerIds)->delete();

        if ($saloonIds->isNotEmpty()) {
            SaloonBranch::query()->whereIn('saloon_id', $saloonIds)->delete();
            User::query()->whereIn('saloon_id', $saloonIds)->delete();
            Saloon::query()->whereIn('id', $saloonIds)->delete();
        }

        AffiliatePartner::query()->whereIn('id', $partnerIds)->delete();
        User::query()->whereIn('id', $userIds)->delete();
        User::query()->where('notes', self::SEED_MARKER)->delete();
    }

    private function purgeLegacyStaticAffiliate(): void
    {
        $legacyUser = User::query()->where('email', 'affiliate@glowdemo.com')->first();
        if ($legacyUser === null) {
            return;
        }

        $partner = AffiliatePartner::query()->where('user_id', $legacyUser->id)->first();
        if ($partner !== null) {
            AffiliateCommission::query()->where('affiliate_partner_id', $partner->id)->delete();
            AffiliateWithdrawalRequest::query()->where('affiliate_partner_id', $partner->id)->delete();
            AffiliateReferral::query()->where('affiliate_partner_id', $partner->id)->delete();

            $saloonIds = Saloon::query()
                ->where('affiliate_partner_id', $partner->id)
                ->pluck('id');

            if ($saloonIds->isNotEmpty()) {
                SaloonBranch::query()->whereIn('saloon_id', $saloonIds)->delete();
                User::query()->whereIn('saloon_id', $saloonIds)->delete();
                Saloon::query()->whereIn('id', $saloonIds)->delete();
            }

            $partner->delete();
        }

        $legacyUser->delete();
    }

    /**
     * @param  list<array{email: string, code: string, display_name: string, referrals: int, commissions: int}>  $created
     */
    private function printCredentials(array $created): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->info('Affiliate demo partners seeded (password: '.DemoDataSeeder::DEMO_PASSWORD.'):');
        $this->command->table(
            ['Display name', 'Email', 'Code', 'Referrals', 'Commissions'],
            array_map(fn (array $row) => [
                $row['display_name'],
                $row['email'],
                $row['code'],
                $row['referrals'],
                $row['commissions'],
            ], $created),
        );
    }
}
