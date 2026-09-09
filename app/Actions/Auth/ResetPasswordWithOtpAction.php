<?php

namespace App\Actions\Auth;

use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\AuthIdentifier;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResetPasswordWithOtpAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): void
    {
        [$user, $channel, $destination] = $this->resolveUserAndChannel($payload);

        $otp = PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('destination', $destination)
            ->where('verification_token', (string) $payload['verification_token'])
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new HttpException(422, 'Verified OTP session not found.');
        }

        if ($otp->expires_at->isPast()) {
            throw new HttpException(422, 'Verified OTP session has expired.');
        }

        $user->forceFill([
            'password' => Hash::make((string) $payload['new_password']),
        ])->save();

        $user->tokens()->delete();

        $otp->forceFill([
            'consumed_at' => now(),
        ])->save();
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
