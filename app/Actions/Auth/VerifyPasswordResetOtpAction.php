<?php

namespace App\Actions\Auth;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\AuthIdentifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyPasswordResetOtpAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{channel: string, destination: string, verification_token: string}
     */
    public function execute(array $payload): array
    {
        [$user, $channel, $destination] = $this->resolveUserAndChannel($payload);

        $otp = PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('destination', $destination)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new HttpException(422, 'OTP request not found.');
        }

        if ($otp->expires_at->isPast()) {
            throw new HttpException(422, 'OTP has expired.');
        }

        $submittedOtp = (string) $payload['otp'];
        $developmentMasterCode = (string) config('otp.development_master_code', '0101');
        $allowDevelopmentMasterCode = (bool) config('otp.allow_development_master_code', false);

        $isValidOtp = Hash::check($submittedOtp, $otp->code_hash)
            || ($allowDevelopmentMasterCode && $submittedOtp === $developmentMasterCode);

        if (! $isValidOtp) {
            throw new HttpException(422, 'Invalid OTP.');
        }

        if (! $otp->verified_at) {
            $otp->forceFill([
                'verified_at' => now(),
                'verification_token' => (string) Str::uuid(),
            ])->save();
        }

        return [
            'channel' => $channel,
            'destination' => $destination,
            'verification_token' => (string) $otp->verification_token,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: User, 1: string, 2: string}
     */
    private function resolveUserAndChannel(array $payload): array
    {
        $login = AuthIdentifier::normalize((string) $payload['login']);

        if (AuthIdentifier::isEmail($login)) {
            $destination = $login;
            $user = User::query()->where('email', $destination)->first();

            if (! $user) {
                throw new HttpException(422, 'No user found for the provided email address.');
            }

            return [$user, 'email', $destination];
        }

        $destination = $login;
        $query = User::query()->where('phone', $destination);

        if ($query->count() > 1) {
            throw new HttpException(422, 'Multiple users found for the provided phone number.');
        }

        $user = $query->first();

        if (! $user) {
            throw new HttpException(422, 'No user found for the provided phone number.');
        }

        return [$user, 'sms', $destination];
    }
}
