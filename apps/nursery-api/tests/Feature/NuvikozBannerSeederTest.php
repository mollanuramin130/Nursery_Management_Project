<?php

namespace Tests\Feature;

use Database\Seeders\NuvikozBannerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NuvikozBannerSeederTest extends TestCase
{
    use RefreshDatabase;

    private function prepareCategories(): void
    {
        foreach (['flowering-plants', 'outdoor-plants'] as $slug) {
            DB::table('categories')->insert(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
        }
    }

    public function test_preview_does_not_publish_banners(): void
    {
        $this->prepareCategories();
        $result = app(NuvikozBannerSeeder::class)->import(true);
        $this->assertSame(3, $result['created']);
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_rerun_preserves_admin_edits_and_soft_deletes(): void
    {
        $this->prepareCategories();
        $seeder = app(NuvikozBannerSeeder::class);
        $this->assertSame(3, $seeder->import()['created']);
        $id = DB::table('banners')->where('meta->seed_key', 'nuvikoz-banner-v1-indoor')->value('id');
        DB::table('banners')->where('id', $id)->update(['title' => 'Owner title', 'status' => 'inactive', 'image_url' => 'https://example.com/owner.webp', 'deleted_at' => now()]);
        $result = $seeder->import();
        $this->assertSame(0, $result['created']);
        $this->assertSame(3, $result['skipped']);
        $this->assertDatabaseCount('banners', 3);
        $this->assertDatabaseHas('banners', ['id' => $id, 'title' => 'Owner title', 'status' => 'inactive']);
        $this->assertNotNull(DB::table('banners')->where('id', $id)->value('deleted_at'));
    }

    public function test_refresh_updates_artwork_without_changing_status_or_creating_duplicates(): void
    {
        $this->prepareCategories();
        $seeder = app(NuvikozBannerSeeder::class);
        $seeder->import();
        $id = DB::table('banners')->where('meta->seed_key', 'nuvikoz-banner-v1-grow')->value('id');
        DB::table('banners')->where('id', $id)->update([
            'title' => 'Previous artwork', 'image_url' => 'https://example.com/grow-v1.webp',
            'status' => 'inactive', 'sort_order' => 42,
        ]);
        $preview = $seeder->import(true, true);
        $this->assertSame(3, $preview['updated']);
        $this->assertDatabaseHas('banners', ['id' => $id, 'title' => 'Previous artwork']);
        $result = $seeder->import(false, true);
        $this->assertSame(0, $result['created']);
        $this->assertSame(3, $result['updated']);
        $this->assertDatabaseCount('banners', 3);
        $this->assertDatabaseHas('banners', [
            'id' => $id, 'title' => 'Garden tools, delivered',
            'link_value' => '/shop?product_type=tool', 'status' => 'inactive', 'sort_order' => 42,
        ]);
    }

}

