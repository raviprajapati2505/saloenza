<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetOtp;
use App\Models\Saloon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForgotPasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_verify_and_reset_password_with_email_otp(): void
    {
        config()->set('otp.expose_code_in_response', true);

        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'phone' => '+919999999999',
            'saloon_id' => $saloon->id,
            'password' => 'OldPass@123',
        ]);

        $requestOtpResponse = $this->postJson('/api/v1/public/forgot-password/request-otp', [
            'login' => $user->email,
        ]);

        $requestOtpResponse
            ->assertOk()
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.destination', $user->email);

        $otp = (string) $requestOtpResponse->json('data.otp_code');

        $verifyOtpResponse = $this->postJson('/api/v1/public/forgot-password/verify-otp', [
            'login' => $user->email,
            'otp' => $otp,
        ]);

        $verifyOtpResponse
            ->assertOk()
            ->assertJsonPath('data.channel', 'email');

        $verificationToken = (string) $verifyOtpResponse->json('data.verification_token');

        $resetPasswordResponse = $this->postJson('/api/v1/public/forgot-password/reset-password', [
            'login' => $user->email,
            'verification_token' => $verificationToken,
            'new_password' => 'NewPass@123',
            'new_password_confirmation' => 'NewPass@123',
        ]);

        $resetPasswordResponse
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('NewPass@123', $user->fresh()->password));

        $otpRecord = PasswordResetOtp::query()->latest('id')->first();

        $this->assertNotNull($otpRecord);
        $this->assertNotNull($otpRecord->verified_at);
        $this->assertNotNull($otpRecord->consumed_at);
    }

    public function test_user_can_request_password_reset_otp_using_phone(): void
    {
        config()->set('otp.expose_code_in_response', true);

        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'phone-owner@example.com',
            'phone' => '+918888888888',
            'saloon_id' => $saloon->id,
        ]);

        $response = $this->postJson('/api/v1/public/forgot-password/request-otp', [
            'login' => $user->phone,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.channel', 'sms')
            ->assertJsonPath('data.destination', $user->phone);

        $this->assertDatabaseHas('password_reset_otps', [
            'user_id' => $user->id,
            'channel' => 'sms',
            'destination' => $user->phone,
        ]);
    }

    public function test_development_master_otp_can_verify_requested_code(): void
    {
        config()->set('otp.allow_development_master_code', true);
        config()->set('otp.development_master_code', '0101');

        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'dev-otp@example.com',
            'phone' => '+917777777777',
            'saloon_id' => $saloon->id,
        ]);

        $this->postJson('/api/v1/public/forgot-password/request-otp', [
            'login' => $user->email,
        ])->assertOk();

        $verifyOtpResponse = $this->postJson('/api/v1/public/forgot-password/verify-otp', [
            'login' => $user->email,
            'otp' => '0101',
        ]);

        $verifyOtpResponse
            ->assertOk()
            ->assertJsonPath('data.channel', 'email');
    }
}
