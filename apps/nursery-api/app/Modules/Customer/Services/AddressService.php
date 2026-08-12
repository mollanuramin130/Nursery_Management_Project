<?php

namespace App\Modules\Customer\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Customer\Models\Address;
use App\Shared\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AddressService
{
    public function list(User $user): array
    {
        return Address::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Address $a) => $a->toApiArray())
            ->values()
            ->all();
    }

    public function create(User $user, array $payload): array
    {
        return DB::transaction(function () use ($user, $payload) {
            if (! empty($payload['is_default'])) {
                Address::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $address = Address::query()->create([
                ...$payload,
                'user_id' => $user->id,
                'country' => $payload['country'] ?? 'IN',
                'is_default' => (bool) ($payload['is_default'] ?? false),
            ]);

            return $address->toApiArray();
        });
    }

    public function update(User $user, int $id, array $payload): array
    {
        $address = $this->owned($user, $id);

        return DB::transaction(function () use ($user, $address, $payload) {
            if (! empty($payload['is_default'])) {
                Address::query()->where('user_id', $user->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->fill($payload)->save();

            return $address->fresh()->toApiArray();
        });
    }

    /**
     * Soft-delete an address owned by the user.
     * If the deleted address was default, no other address is auto-promoted
     * (authoritative rule — clients must not invent a new default).
     */
    public function delete(User $user, int $id): void
    {
        $this->owned($user, $id)->delete();
    }

    public function owned(User $user, int $id): Address
    {
        $address = Address::query()->where('user_id', $user->id)->whereKey($id)->first();
        if (! $address) {
            throw new NotFoundHttpException('Address not found');
        }

        return $address;
    }

    public function updateProfile(User $user, array $payload): array
    {
        if (isset($payload['phone'])) {
            $exists = User::query()
                ->where('phone', $payload['phone'])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                throw new ApiException('Phone already in use', 409, 'CONFLICT');
            }
        }

        $user->fill(collect($payload)->only(['name', 'phone'])->all())->save();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }
}
