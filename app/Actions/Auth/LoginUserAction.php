<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\AuthIdentifier;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{user: User, token: string, should_onboard: bool}
     */
    public function execute(array $payload): array
    {
        $login = AuthIdentifier::normalize((string) $payload['login']);
        $password = (string) $payload['password'];
        $deviceName = trim((string) ($payload['device_name'] ?? 'web'));

        $user = $this->resolveUser($login);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new HttpException(422, 'Invalid login or password.');
        }

        if (! $user->is_active) {
            throw new HttpException(422, 'Your account has been deactivated.');
        }

        $user->loadMissing(['saloon', 'role.permissions', 'affiliatePartner']);

        $token = $this->userRepository->createToken($user, $deviceName !== '' ? $deviceName : 'web');

        return [
            'user' => $user,
            'token' => $token,
            'should_onboard' => $user->shouldOnboard(),
        ];
    }

    private function resolveUser(string $login): ?User
    {
        if (AuthIdentifier::isEmail($login)) {
            return User::query()
                ->with(['saloon', 'role.permissions', 'affiliatePartner'])
                ->where('email', $login)
                ->first();
        }

        $query = User::query()
            ->with(['saloon', 'role.permissions', 'affiliatePartner'])
            ->where('phone', $login);

        if ($query->count() > 1) {
            throw new HttpException(422, 'Multiple users found for the provided phone number.');
        }

        return $query->first();
    }
}
