<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $slugs = [
            'hibiscus',
            'arabian-jasmine',
            'bougainvillea',
            'ixora',
            'marigold',
            'periwinkle',
            'tulsi',
            'mint',
            'curry-leaf',
            'lemongrass',
            'jade-plant',
            'aloe-vera',
            'zebra-haworthia',
            'echeveria',
            'elephant-bush',
            'guava-sapling',
            'lemon-sapling',
            'pomegranate-sapling',
            'mango-sapling',
            'papaya-sapling',
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
        $baseUrl = rtrim((string) config('app.url'), '/');
        $slugs = [
            'hibiscus', 'arabian-jasmine', 'bougainvillea', 'ixora', 'marigold', 'periwinkle', 'tulsi',
            'mint', 'curry-leaf', 'lemongrass', 'jade-plant', 'aloe-vera', 'zebra-haworthia', 'echeveria',
            'elephant-bush', 'guava-sapling', 'lemon-sapling', 'pomegranate-sapling', 'mango-sapling', 'papaya-sapling'
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
