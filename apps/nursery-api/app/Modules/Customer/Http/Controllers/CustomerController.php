<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Customer\Services\AddressService;
use App\Modules\Customer\Services\PreferenceService;
use App\Modules\Order\Services\ReturnService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly AddressService $addresses,
        private readonly PreferenceService $preferences,
        private readonly ReturnService $returns,
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->addresses->updateProfile($user, $validated),
            'Profile updated successfully',
        );
    }

    public function getPreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->preferences->get($user),
            'Preferences retrieved successfully',
        );
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'preferred_language' => ['sometimes', 'string', 'in:en,hi'],
            'notify_orders' => ['sometimes', 'boolean'],
            'notify_promotions' => ['sometimes', 'boolean'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_push' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->preferences->update($user, $validated),
            'Preferences updated successfully',
        );
    }

    public function listReturns(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->returns->listForUser($user, (int) $request->query('per_page', 20));

        return ApiResponse::success($result['data'], 'Returns retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function listAddresses(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->addresses->list($user),
            'Addresses retrieved successfully',
        );
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->addresses->create($user, $validated),
            'Address created successfully',
            201,
        );
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'nullable', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'line1' => ['sometimes', 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:100'],
            'state' => ['sometimes', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'string', 'max:20'],
            'country' => ['sometimes', 'string', 'size:2'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->addresses->update($user, $id, $validated),
            'Address updated successfully',
        );
    }

    public function destroyAddress(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->addresses->delete($user, $id);

        return ApiResponse::success(null, 'Address deleted successfully');
    }
}
