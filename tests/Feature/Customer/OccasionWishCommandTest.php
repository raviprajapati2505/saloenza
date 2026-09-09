<?php

namespace Tests\Feature\Customer;

use App\Mail\CustomerOccasionWishMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OccasionWishCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_birthday_and_anniversary_emails_once(): void
    {
        Mail::fake();

        $owner = $this->createOwnerUser(['phone' => '+917000000301']);
        $salon = $owner->saloon;
        $today = now()->toDateString();

        $birthday = Customer::query()->create([
            'name' => 'Birthday Client',
            'email' => 'birthday.client@example.com',
            'phone' => '+919555555501',
            'birthday' => $today,
            'is_active' => true,
        ]);
        $birthday->attachSaloon((int) $salon->id);

        $anniversary = Customer::query()->create([
            'name' => 'Anniversary Client',
            'email' => 'anniversary.client@example.com',
            'phone' => '+919555555502',
            'anniversary' => $today,
            'is_active' => true,
        ]);
        $anniversary->attachSaloon((int) $salon->id);

        Artisan::call('customers:send-occasion-wishes');

        Mail::assertSent(CustomerOccasionWishMail::class, 2);
        Mail::assertSent(CustomerOccasionWishMail::class, function (CustomerOccasionWishMail $mail) use ($birthday): bool {
            return $mail->customer->is($birthday) && $mail->occasion === 'birthday';
        });
        Mail::assertSent(CustomerOccasionWishMail::class, function (CustomerOccasionWishMail $mail) use ($anniversary): bool {
            return $mail->customer->is($anniversary) && $mail->occasion === 'anniversary';
        });

        $this->assertSame($today, $birthday->fresh()->last_birthday_wish_on?->toDateString());
        $this->assertSame($today, $anniversary->fresh()->last_anniversary_wish_on?->toDateString());

        Mail::fake();
        Artisan::call('customers:send-occasion-wishes');
        Mail::assertNothingSent();
    }
}
