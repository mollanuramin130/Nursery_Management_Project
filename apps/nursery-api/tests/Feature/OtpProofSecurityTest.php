<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\VerificationCode;
use App\Modules\Auth\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OtpProofSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function proof(): string
    {
        config(['auth.otp.dev_code' => '123456']);
        $otp = app(OtpService::class);
        $otp->sendCode('9876543210', 'reset');

        return $otp->issueVerifiedToken($otp->verifyCode('9876543210', '123456', 'reset'));
    }

    public function test_verified_proof_cannot_be_replayed(): void
    {
        $token = $this->proof();
        app(OtpService::class)->consumeVerifiedToken($token, 'reset', '9876543210');
        $this->assertNotNull(VerificationCode::first()->consumed_at);
        $this->expectException(ValidationException::class);
        app(OtpService::class)->consumeVerifiedToken($token, 'reset', '9876543210');
    }

    public function test_failed_business_transaction_does_not_consume_proof(): void
    {
        $token = $this->proof();
        try {
            DB::transaction(function () use ($token) {
                app(OtpService::class)->consumeVerifiedToken($token, 'reset', '9876543210');
                throw new \RuntimeException('Simulated business failure');
            });
        } catch (\RuntimeException) {
        }
        $this->assertNull(VerificationCode::first()->consumed_at);
        $this->assertIsArray(app(OtpService::class)->consumeVerifiedToken($token, 'reset', '9876543210'));
    }

    public function test_wrong_purpose_does_not_consume_proof(): void
    {
        $token = $this->proof();
        try {
            app(OtpService::class)->consumeVerifiedToken($token, 'register', '9876543210');
            $this->fail('A reset proof must not authorize registration');
        } catch (ValidationException) {
            $this->assertNull(VerificationCode::first()->consumed_at);
        }
    }

    public function test_production_sms_does_not_report_delivery_without_a_provider(): void
    {
        $this->app->instance('env', 'production');
        $this->postJson('/api/v1/auth/otp/send', ['mobile' => '9876543210', 'purpose' => 'register'])
            ->assertStatus(503)->assertJsonPath('meta.error_code', 'SMS_UNAVAILABLE');
        $this->assertDatabaseCount('verification_codes', 0);
    }
}
