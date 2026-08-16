<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

class AuthenticationService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * A non-secret hash used to keep failed login timing consistent when an email is unknown.
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$d3Ky9gE5KVbwCwMKC2D4h.Gtm.xGJQmWKkiW2Ijr2BMY4DF04I5S.';

    /**
     * @param  array{fullName: string, businessName: string, email: string, password: string}  $attributes
     */
    public function register(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::query()->create([
                'name' => $attributes['fullName'],
                'business_name' => $attributes['businessName'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
                'terms_accepted_at' => now(),
            ]);
            $this->subscriptions->assignDefault($user);

            return $user;
        });
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();
        $passwordHash = $user?->password ?? self::DUMMY_PASSWORD_HASH;

        if (! Hash::check($password, $passwordHash) || ! $user) {
            return null;
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }

        return $user;
    }

    /**
     * @return array{accessToken: string, expiresAt: CarbonImmutable}
     */
    public function issueToken(User $user, bool $remember): array
    {
        $expiresInMinutes = (int) config(
            $remember
                ? 'authentication.token.remember_expires_in_minutes'
                : 'authentication.token.expires_in_minutes',
        );
        $expiresAt = now()->toImmutable()->addMinutes($expiresInMinutes);

        $user->tokens()->where('expires_at', '<', now())->delete();

        /** @var NewAccessToken $token */
        $token = $user->createToken(
            (string) config('authentication.token.default_name'),
            ['*'],
            $expiresAt,
        );

        return [
            'accessToken' => $token->plainTextToken,
            'expiresAt' => $expiresAt,
        ];
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
