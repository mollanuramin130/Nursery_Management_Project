<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Wishlist\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * QA-30 — wishlist soft-delete idempotency (QA-29-003 root cause).
 */
class Qa30WishlistAndImagesTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::query()->create([
            'name' => 'QA30 Customer',
            'email' => 'qa30-cust-'.uniqid().'@example.com',
            'password' => Hash::make('Secret@123'),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->roles()->sync(Role::query()->where('slug', 'customer')->pluck('id'));

        return $user->fresh();
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'QA30 Plant',
            'slug' => 'qa30-plant-'.uniqid(),
            'sku' => 'QA30-SKU-'.uniqid(),
            'product_type' => 'plant',
            'status' => 'active',
            'price' => 199,
            'currency' => 'INR',
            'stock_status' => 'in_stock',
        ]);
    }

    #[Test]
    public function wishlist_add_restores_soft_deleted_row_instead_of_500(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $token = JWTAuth::fromUser($user);

        $row = Wishlist::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
        $row->delete();
        $this->assertSoftDeleted('wishlists', ['id' => $row->id]);

        $this->withToken($token)
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertSuccessful()
            ->assertJsonPath('data.product_id', $product->id);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function wishlist_add_still_returns_409_for_active_duplicate(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertSuccessful();
        $this->withToken($token)
            ->postJson('/api/v1/wishlist', ['product_id' => $product->id])
            ->assertStatus(409);
    }
}
