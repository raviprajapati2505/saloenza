<?php

namespace App\Actions\Auth;

use App\Contracts\Notifications\OtpNotificationServiceInterface;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Support\AuthIdentifier;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequestPasswordResetOtpAction
{
    public function __construct(
        private readonly OtpNotificationServiceInterface $otpNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{channel: string, destination: string, otp_code: string, expires_at: string}
     */
    public function execute(array $payload): array
    {
        [$user, $channel, $destination] = $this->resolveUserAndChannel($payload);

        PasswordResetOtp::query()
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('destination', $destination)
            ->delete();

        $code = $this->generateCode();
        $expiresAt = now()->addMinutes((int) config('otp.expiry_minutes', 10));

        PasswordResetOtp::query()->create([
            'user_id' => $user->id,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        $this->otpNotificationService->sendPasswordResetOtp(
            $channel,
            $destination,
            $code,
            $user->saloon,
        );

        return [
            'channel' => $channel,
            'destination' => $destination,
            'otp_code' => $code,
            'expires_at' => $expiresAt->toISOString(),
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

        // TODO: WhatsApp — deliver password-reset OTP via WhatsApp for phone logins.
        if (filled($user->email)) {
            return [$user, 'email', (string) $user->email];
        }

        throw new HttpException(
            422,
            'SMS verification is no longer available. Add an email to your account or contact support to reset your password.',
        );
    }

    private function generateCode(): string
    {
        $length = max(4, (int) config('otp.code_length', 4));
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }
}
