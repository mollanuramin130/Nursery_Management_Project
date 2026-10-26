<?php

namespace Database\Seeders;

use App\Modules\Catalog\Services\HomeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class NuvikozBannerSeeder extends Seeder
{
    public function run(): void
    {
        $result = $this->import();
        $this->command?->info("Created {$result['created']} banners; skipped {$result['skipped']} existing banners.");
    }

    public function import(bool $dryRun = false, bool $refreshArtwork = false): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('APP_URL must be an absolute URL before importing banners.');
        }
        $banners = [
            ['key' => 'indoor', 'title' => 'Plants, delivered to your door', 'type' => 'url', 'value' => '/shop'],
            ['key' => 'flowers', 'title' => 'A plant for every corner', 'type' => 'catalog_filter', 'value' => '/shop?product_type=plant'],
            ['key' => 'grow', 'title' => 'Garden tools, delivered', 'type' => 'catalog_filter', 'value' => '/shop?product_type=tool'],
        ];
        foreach ($banners as $banner) {
            if (! is_file(base_path('public/banners/nuvikoz/'.$banner['key'].'-v2.webp'))) {
                throw new RuntimeException('Missing banner artwork: '.$banner['key']);
            }
            if ($banner['type'] === 'category' && ! DB::table('categories')->where('slug', $banner['value'])->where('status', 'active')->whereNull('deleted_at')->exists()) {
                throw new RuntimeException('Active category missing: '.$banner['value'].'. Import the Nuvikoz plant catalog first.');
            }
        }
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            foreach ($banners as $index => $banner) {
                $key = 'nuvikoz-banner-v1-'.$banner['key'];
                $url = $baseUrl.'/banners/nuvikoz/'.$banner['key'].'-v2.webp';
                // Stable v1 seed keys identify the same banners across artwork revisions.
                $existing = DB::table('banners')->where('meta->seed_key', $key)
                    ->orWhereIn('image_url', [$url, $baseUrl.'/banners/nuvikoz/'.$banner['key'].'-v1.webp'])->first();
                if ($existing) {
                    if ($refreshArtwork && $existing->deleted_at === null) {
                        $meta = json_decode($existing->meta ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?? [];
                        DB::table('banners')->where('id', $existing->id)->update([
                            'title' => $banner['title'], 'image_url' => $url,
                            'link_type' => $banner['type'], 'link_value' => $banner['value'],
                            'meta' => json_encode(array_merge($meta, ['seed_key' => $key, 'artwork_version' => 2]), JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                        $result['updated']++;
                    } else {
                        $result['skipped']++;
                    }
                    continue;
                }
                DB::table('banners')->insert([
                    'title' => $banner['title'], 'image_url' => $url, 'placement' => 'home',
                    'link_type' => $banner['type'], 'link_value' => $banner['value'],
                    'sort_order' => -300 + $index * 100, 'status' => 'active',
                    'meta' => json_encode(['seed_key' => $key, 'artwork_version' => 2, 'artwork' => 'AI-generated styled garden inspiration; not an exact product photograph.'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $result['created']++;
            }
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            if (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
            throw $e;
        }
        if (! $dryRun) {
            HomeService::forgetCache();
        }

        return $result;
    }
}

