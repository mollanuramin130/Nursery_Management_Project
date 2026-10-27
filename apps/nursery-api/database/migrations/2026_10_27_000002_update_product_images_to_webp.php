<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $slugs = [
            'aglaonema-green',
            'areca-palm',
            'golden-money-plant',
            'heartleaf-philodendron',
            'monstera',
            'peace-lily',
            'rubber-plant',
            'snake-plant',
            'spider-plant',
            'zz-plant',
        ];

        foreach ($slugs as $slug) {
            $starterUrl = $baseUrl . '/catalog/starter/' . $slug . '.svg';
            $detailUrl = $baseUrl . '/catalog/detail/' . $slug . '.webp';

            DB::table('product_images')
                ->where('url', $starterUrl)
                ->update([
                    'url' => $detailUrl,
                    'alt' => str_replace('-', ' ', ucfirst($slug)) . ' product photograph'
                ]);
        }
    }

    public function down(): void
    {
        // Revert is not strictly necessary for this data patch, but we can do it
        $baseUrl = rtrim((string) config('app.url'), '/');
        $slugs = [
            'aglaonema-green', 'areca-palm', 'golden-money-plant', 'heartleaf-philodendron',
            'monstera', 'peace-lily', 'rubber-plant', 'snake-plant', 'spider-plant', 'zz-plant'
        ];

        foreach ($slugs as $slug) {
            $starterUrl = $baseUrl . '/catalog/starter/' . $slug . '.svg';
            $detailUrl = $baseUrl . '/catalog/detail/' . $slug . '.webp';

            DB::table('product_images')
                ->where('url', $detailUrl)
                ->update([
                    'url' => $starterUrl,
                    'alt' => str_replace('-', ' ', ucfirst($slug)) . ' — illustrative artwork, not a product photograph'
                ]);
        }
    }
};
