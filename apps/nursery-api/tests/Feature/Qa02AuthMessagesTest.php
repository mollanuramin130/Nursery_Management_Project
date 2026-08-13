<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class Qa02AuthMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $status = 'active', string $email = 'qa-auth@example.com'): User
    {
        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer'],
        );

        $user = User::query()->create([
            'name' => 'QA Auth',
            'email' => $email,
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => $status])->save();

        return $user;
    }

    public function test_valid_login_returns_tokens(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'qa-auth@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'web'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'user'],
            ]);
    }

    public function test_invalid_login_returns_credentials_message(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'qa-auth@example.com',
            'password' => 'WrongPass1',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid email or password')
            ->assertJsonPath('meta.error_code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_unknown_email_returns_same_credentials_message(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'Secret@123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password')
            ->assertJsonPath('meta.error_code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_blocked_login_returns_blocked_message(): void
    {
        $this->makeUser('blocked');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'qa-auth@example.com',
            'password' => 'Secret@123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Account is blocked')
            ->assertJsonPath('meta.error_code', 'AUTH_ACCOUNT_BLOCKED');
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Weak',
            'email' => 'weak-pass@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Dup',
            'email' => 'qa-auth@example.com',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertStatus(409);
    }

    public function test_refresh_and_logout(): void
    {
        $this->makeUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'qa-auth@example.com',
            'password' => 'Secret@123',
            'device' => ['platform' => 'web'],
        ])->assertOk()->json('data');

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ])->assertOk()->json('data');

        $this->assertNotEmpty($refresh['access_token']);
        $this->assertNotEmpty($refresh['refresh_token']);

        $this->withHeader('Authorization', 'Bearer '.$refresh['access_token'])
            ->postJson('/api/v1/auth/logout', [
                'refresh_token' => $refresh['refresh_token'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refresh['refresh_token'],
        ])
            ->assertStatus(401)
            ->assertJsonPath('meta.error_code', 'AUTH_REFRESH_INVALID');
    }

    public function test_invalid_refresh_token_message(): void
    {
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => str_repeat('a', 64),
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid or expired refresh token')
            ->assertJsonPath('meta.error_code', 'AUTH_REFRESH_INVALID');
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'NewSecret@123',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertStatus(422);
    }
}
