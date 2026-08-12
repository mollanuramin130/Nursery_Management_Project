<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\LogoutRequest;
use App\Modules\Auth\Http\Requests\RefreshTokenRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Cart\Services\CartService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CartService $cartService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $this->authService->register($request->validated());
        $this->mergeGuestCartIfNeeded($request, $data);

        return ApiResponse::success($data, 'Registration successful', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $this->authService->login($request->validated());
        $this->mergeGuestCartIfNeeded($request, $data);

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
