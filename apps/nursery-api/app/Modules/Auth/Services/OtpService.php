<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\VerificationCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    private function otpTtlMinutes(): int
    {
        return (int) config('auth.otp.ttl_minutes', 10);
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.otp.max_attempts', 5);
    }

    private function cooldownSeconds(): int
    {
        return (int) config('auth.otp.cooldown_seconds', 30);
    }

    /**
     * Send an OTP code. Returns the verification_id for the client to pass back.
     *
     * @param  string  $mobile  Normalized E.164 phone number
     * @param  string  $purpose  register|login|reset
     */
    public function sendCode(string $mobile, string $purpose): array
    {
        $mobile = self::normalizeMobile($mobile);

        $this->enforceResendCooldown($mobile, $purpose);

        // Invalidate any previous unverified codes for the same identifier+purpose.
        VerificationCode::query()
            ->where('identifier', $mobile)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['expires_at' => now()]);

        $code = $this->generateCode();
        $codeHash = hash('sha256', $code);

        $record = VerificationCode::query()->create([
            'user_id' => User::query()->where('phone', $mobile)->value('id'),
            'identifier' => $mobile,
            'identifier_type' => 'phone',
            'purpose' => $purpose,
            'code_hash' => $codeHash,
            'max_attempts' => $this->maxAttempts(),
            'expires_at' => now()->addMinutes($this->otpTtlMinutes()),
        ]);

        $this->dispatchSms($mobile, $code);

        return [
            'verification_id' => $record->id,
            'expires_in' => $this->otpTtlMinutes() * 60,
            'mobile' => self::maskMobile($mobile),
        ];
    }

    /**
     * Verify an OTP code. Returns the verification record on success.
     */
    public function verifyCode(string $mobile, string $code, string $purpose): VerificationCode
    {
        $mobile = self::normalizeMobile($mobile);

        $record = VerificationCode::query()
            ->where('identifier', $mobile)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $record) {
            throw ValidationException::withMessages([
                'code' => ['No pending verification found. Please request a new code.'],
            ]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['This code has expired. Please request a new one.'],
            ]);
        }

        if ($record->hasExceededAttempts()) {
            throw ValidationException::withMessages([
                'code' => ['Too many incorrect attempts. Please request a new code.'],
            ]);
        }

        $record->increment('attempts');

        if (! hash_equals($record->code_hash, hash('sha256', $code))) {
            $remaining = $record->max_attempts - $record->attempts;
            throw ValidationException::withMessages([
                'code' => ["Incorrect code. {$remaining} attempt(s) remaining."],
            ]);
        }

        $record->forceFill(['verified_at' => now()])->save();

        return $record;
    }

    /**
     * Create a signed, short-lived token proving OTP was verified server-side.
     * Clients pass this to register/reset endpoints.
     */
    public function issueVerifiedToken(VerificationCode $record): string
    {
        $payload = json_encode([
            'vid' => $record->id,
            'identifier' => $record->identifier,
            'purpose' => $record->purpose,
            'exp' => now()->addMinutes(10)->timestamp,
        ]);

        $secret = config('app.key');
        $signature = hash_hmac('sha256', $payload, $secret);

        return base64_encode($payload).'.'.$signature;
    }

    /**
     * Validate a verified-OTP token and return the decoded payload.
     */
    public function validateVerifiedToken(string $token, string $expectedPurpose, string $expectedMobile): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'otp_verified_token' => ['Invalid verification token.'],
            ]);
        }

        [$encodedPayload, $signature] = $parts;
        $secret = config('app.key');

        if (! hash_equals(hash_hmac('sha256', base64_decode($encodedPayload), $secret), $signature)) {
            throw ValidationException::withMessages([
                'otp_verified_token' => ['Invalid verification token.'],
            ]);
        }

        $payload = json_decode(base64_decode($encodedPayload), true);

        if (! is_array($payload) || ($payload['exp'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'otp_verified_token' => ['Verification token has expired. Please verify your mobile again.'],
            ]);
        }

        $normalizedMobile = self::normalizeMobile($expectedMobile);
        if (($payload['purpose'] ?? '') !== $expectedPurpose || ($payload['identifier'] ?? '') !== $normalizedMobile) {
            throw ValidationException::withMessages([
                'otp_verified_token' => ['Verification token does not match this request.'],
            ]);
        }

        return $payload;
    }

    /**
     * Normalize an Indian mobile number to +91XXXXXXXXXX format.
     */
    public static function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return '+91'.substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }

        // Fallback: return as-is with + prefix if not already present.
        return str_starts_with($mobile, '+') ? $mobile : '+'.$digits;
    }

    public static function maskMobile(string $mobile): string
    {
        if (strlen($mobile) < 6) {
            return $mobile;
        }

        return substr($mobile, 0, 4).str_repeat('*', strlen($mobile) - 6).substr($mobile, -2);
    }

    private function generateCode(): string
    {
        if (app()->environment('local', 'testing')) {
            $devCode = config('auth.otp.dev_code');
            if ($devCode) {
                return (string) $devCode;
            }
        }

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function dispatchSms(string $mobile, string $code): void
    {
        if (app()->environment('local', 'testing')) {
            Log::info("[OTP] Code for {$mobile}: {$code} (dev only — never log in production)");

            return;
        }

        // Production: integrate real SMS provider (e.g., MSG91, Twilio).
        // For now, log a warning so we know it's not configured.
        Log::warning("[OTP] SMS dispatch not configured for production. Mobile: {$mobile}");
    }

    private function enforceResendCooldown(string $mobile, string $purpose): void
    {
        $recent = VerificationCode::query()
            ->where('identifier', $mobile)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subSeconds($this->cooldownSeconds()))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'mobile' => ['Please wait before requesting another code.'],
            ]);
        }
    }
}
