<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\OtpService;
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
        $mobile = OtpService::normalizeMobile($payload['mobile']);

        if (User::query()->where('phone', $mobile)->exists()) {
            throw new ConflictHttpException('Mobile number already registered');
        }

        if (! empty($payload['email']) && User::query()->where('email', $payload['email'])->exists()) {
            throw new ConflictHttpException('Email already registered');
        }

        return DB::transaction(function () use ($payload, $mobile) {
            $user = new User([
                'name' => $payload['name'],
                'email' => $payload['email'] ?? null,
                'phone' => $mobile,
                'password' => $payload['password'],
            ]);
            $user->status = 'active';
            $user->phone_verified_at = now();
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

    public function loginViaOtp(string $mobile, array $device = []): array
    {
        $mobile = OtpService::normalizeMobile($mobile);
        $user = User::query()->where('phone', $mobile)->first();

        if (! $user) {
            throw new AuthenticationException('No account found with this mobile number');
        }

        if (! $user->isActive()) {
            throw new AuthenticationException('Account is blocked');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->issueTokenPair($user, $device);
    }

    public function resetPasswordViaMobile(string $mobile, string $newPassword): void
    {
        $mobile = OtpService::normalizeMobile($mobile);
        $user = User::query()->where('phone', $mobile)->first();

        if (! $user) {
            throw new AuthenticationException('No account found with this mobile number');
        }

        $user->forceFill(['password' => $newPassword])->save();

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function login(array $payload): array
    {
        if (! empty($payload['mobile'])) {
            $mobile = OtpService::normalizeMobile($payload['mobile']);
            $user = User::query()->where('phone', $mobile)->first();
            $failMsg = 'Invalid mobile number or password';
        } else {
            $user = User::query()->where('email', $payload['email'])->first();
            $failMsg = 'Invalid email or password';
        }

        if (! $user || ! Hash::check($payload['password'], $user->password)) {
            throw new AuthenticationException($failMsg);
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
            'phone_verified' => $user->phone_verified_at !== null,
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
