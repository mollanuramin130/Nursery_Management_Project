<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function register(array $payload): array
    {
        if (User::query()->where('email', $payload['email'])->exists()) {
            throw new ConflictHttpException('Email already registered');
        }

        if (! empty($payload['phone']) && User::query()->where('phone', $payload['phone'])->exists()) {
            throw new ConflictHttpException('Phone already registered');
        }

        return DB::transaction(function () use ($payload) {
            $user = new User([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'password' => $payload['password'],
            ]);
            $user->status = 'active';
            $user->save();

            $customerRole = Role::query()->where('slug', 'customer')->first();
            if ($customerRole) {
                $user->roles()->attach($customerRole->id);
            }

            $user->customerProfile()->create([
                'preferred_language' => 'en',
                'marketing_opt_in' => false,
            ]);

            $pair = $this->issueTokenPair($user, $payload['device'] ?? []);

            // Best-effort welcome journey — never fails registration.
            try {
                app(\App\Modules\Marketing\Services\MarketingAutomationService::class)->sendWelcome($user);
            } catch (\Throwable) {
            }

            return $pair;
        });
    }

    public function login(array $payload): array
    {
        $user = User::query()->where('email', $payload['email'])->first();

        if (! $user || ! Hash::check($payload['password'], $user->password)) {
            throw new AuthenticationException('Invalid email or password');
        }

        if (! $user->isActive()) {
            throw new AuthenticationException('Account is blocked');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->issueTokenPair($user, $payload['device'] ?? []);
    }

    public function refresh(string $refreshToken): array
    {
        $hash = hash('sha256', $refreshToken);
        $existing = RefreshToken::query()->where('token_hash', $hash)->first();

        if (! $existing || ! $existing->isValid()) {
            throw new AuthenticationException('Invalid or expired refresh token');
        }

        $user = $existing->user;
        if (! $user || ! $user->isActive()) {
            throw new AuthenticationException('Invalid or expired refresh token');
        }

        return DB::transaction(function () use ($existing, $user) {
            $pair = $this->issueTokenPair($user, [
                'device_id' => $existing->device_id,
                'platform' => $existing->platform,
            ]);

            $existing->forceFill([
                'revoked_at' => now(),
                'replaced_by_token_id' => RefreshToken::query()
                    ->where('token_hash', hash('sha256', $pair['refresh_token']))
                    ->value('id'),
            ])->save();

            return [
                'token_type' => $pair['token_type'],
                'access_token' => $pair['access_token'],
                'refresh_token' => $pair['refresh_token'],
                'expires_in' => $pair['expires_in'],
            ];
        });
    }

    public function logout(User $user, string $refreshToken, bool $allDevices = false): void
    {
        // Invalidate current access JWT (blacklist) so logout takes effect immediately.
        try {
            $token = JWTAuth::getToken();
            if ($token) {
                JWTAuth::invalidate($token);
            }
        } catch (\Throwable) {
            // Token may already be expired/invalid — still revoke refresh tokens.
        }

        if ($allDevices) {
            RefreshToken::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return;
        }

        $hash = hash('sha256', $refreshToken);
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function forgotPassword(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $payload): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $payload['email'],
                'password' => $payload['password'],
                'password_confirmation' => $payload['password_confirmation'] ?? $payload['password'],
                'token' => $payload['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();

                RefreshToken::query()
                    ->where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }
    }

    public function me(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->roleSlugs(),
            'permissions' => $user->permissionSlugs(),
        ];
    }

    private function issueTokenPair(User $user, array $device = []): array
    {
        $accessToken = JWTAuth::fromUser($user);
        $plainRefresh = Str::random(64);
        $ttlMinutes = (int) env('JWT_REFRESH_TTL', 129600);

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainRefresh),
            'device_id' => $device['device_id'] ?? null,
            'platform' => $device['platform'] ?? null,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        $user->loadMissing('roles');

        return [
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'refresh_token' => $plainRefresh,
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roleSlugs(),
            ],
        ];
    }
}
