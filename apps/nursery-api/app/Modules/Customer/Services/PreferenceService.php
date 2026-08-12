<?php

namespace App\Modules\Customer\Services;

use App\Modules\Auth\Models\CustomerProfile;
use App\Modules\Auth\Models\User;

class PreferenceService
{
    /**
     * @return array<string, mixed>
     */
    public function get(User $user): array
    {
        $profile = $this->profile($user);
        $meta = is_array($profile->meta) ? $profile->meta : [];

        return [
            'marketing_opt_in' => (bool) $profile->marketing_opt_in,
            'preferred_language' => $profile->preferred_language ?: 'en',
            'notify_orders' => (bool) ($meta['notify_orders'] ?? true),
            'notify_promotions' => (bool) ($meta['notify_promotions'] ?? (bool) $profile->marketing_opt_in),
            'notify_email' => (bool) ($meta['notify_email'] ?? true),
            'notify_push' => (bool) ($meta['notify_push'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(User $user, array $payload): array
    {
        $profile = $this->profile($user);
        $meta = is_array($profile->meta) ? $profile->meta : [];

        if (array_key_exists('marketing_opt_in', $payload)) {
            $profile->marketing_opt_in = (bool) $payload['marketing_opt_in'];
        }
        if (array_key_exists('preferred_language', $payload)) {
            $profile->preferred_language = $payload['preferred_language'];
        }

        foreach (['notify_orders', 'notify_promotions', 'notify_email', 'notify_push'] as $key) {
            if (array_key_exists($key, $payload)) {
                $meta[$key] = (bool) $payload[$key];
            }
        }

        $profile->meta = $meta;
        $profile->save();

        return $this->get($user->fresh());
    }

    private function profile(User $user): CustomerProfile
    {
        $profile = $user->customerProfile;
        if ($profile) {
            return $profile;
        }

        return CustomerProfile::query()->create([
            'user_id' => $user->id,
            'marketing_opt_in' => false,
            'preferred_language' => 'en',
            'meta' => [],
        ]);
    }
}
