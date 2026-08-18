<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\LogoutRequest;
use App\Modules\Auth\Http\Requests\OtpLoginRequest;
use App\Modules\Auth\Http\Requests\RefreshTokenRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordViaMobileRequest;
use App\Modules\Auth\Http\Requests\SendOtpRequest;
use App\Modules\Auth\Http\Requests\VerifyOtpRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\OtpService;
use App\Modules\Cart\Services\CartService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CartService $cartService,
        private readonly OtpService $otpService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->validateVerifiedToken(
            $validated['otp_verified_token'],
            'register',
            $validated['mobile'],
        );

        $data = $this->authService->register($validated);
        $this->mergeGuestCartIfNeeded($request, $data);

        return ApiResponse::success($data, 'Registration successful', 201);
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $data = $this->otpService->sendCode(
            $request->validated('mobile'),
            $request->validated('purpose'),
        );

        return ApiResponse::success($data, 'Verification code sent');
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $record = $this->otpService->verifyCode(
            $validated['mobile'],
            $validated['code'],
            $validated['purpose'],
        );

        $token = $this->otpService->issueVerifiedToken($record);

        return ApiResponse::success([
            'verified' => true,
            'otp_verified_token' => $token,
            'mobile' => OtpService::maskMobile(OtpService::normalizeMobile($validated['mobile'])),
        ], 'Mobile verified');
    }

    public function otpLogin(OtpLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->verifyCode(
            $validated['mobile'],
            $validated['code'],
            'login',
        );

        $data = $this->authService->loginViaOtp(
            $validated['mobile'],
            $validated['device'] ?? [],
        );
        $this->mergeGuestCartIfNeeded($request, $data);

        return ApiResponse::success($data, 'Login successful');
    }

    public function resetPasswordViaMobile(ResetPasswordViaMobileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->otpService->validateVerifiedToken(
            $validated['otp_verified_token'],
            'reset',
            $validated['mobile'],
        );

        $this->authService->resetPasswordViaMobile(
            $validated['mobile'],
            $validated['password'],
        );

        return ApiResponse::success(null, 'Password reset successful');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (app()->environment('local')) {
            \Illuminate\Support\Facades\Log::debug('[AUTH] login request started');
        }

        $data = $this->authService->login($request->validated());
        $this->mergeGuestCartIfNeeded($request, $data);

        if (app()->environment('local')) {
            \Illuminate\Support\Facades\Log::debug('[AUTH] login request completed');
        }

        return ApiResponse::success($data, 'Login successful');
    }

    private function mergeGuestCartIfNeeded(Request $request, array $authData): void
    {
        $userId = $authData['user']['id'] ?? null;
        if (! $userId) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $this->cartService->mergeGuestCart($user, $request->header('X-Cart-Token'));
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $data = $this->authService->refresh($request->validated('refresh_token'));

        return ApiResponse::success($data, 'Token refreshed');
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout(
            $user,
            $request->validated('refresh_token'),
            (bool) $request->boolean('all_devices'),
        );

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return ApiResponse::success(null, 'If the email exists, a reset link has been sent');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return ApiResponse::success(null, 'Password reset successful');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->authService->me($user),
            'Profile retrieved successfully',
        );
    }
}
