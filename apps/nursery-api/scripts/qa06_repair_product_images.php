<?php

/**
 * QA-06 — Replace known-dead Unsplash seed URLs in product_images.
 * Safe data repair (no schema change). Re-runnable / idempotent.
 *
 * Usage: php scripts/qa06_repair_product_images.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$replacements = [
    // QA-30: do not map Tulsi's dead URL onto the shared Aloe/succulent asset.
    '1466692476866-aef1dfb1e735' => 'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000',
    '1593482892290-f54927ae2b7a' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000',
    '1463936575829-25148e1670d9' => 'https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=1000',
];

$updated = 0;
foreach ($replacements as $deadId => $liveUrl) {
    $n = DB::table('product_images')
        ->where('url', 'like', '%'.$deadId.'%')
        ->update(['url' => $liveUrl, 'updated_at' => now()]);
    $updated += $n;
    echo "{$deadId}: {$n} row(s)\n";
}

if (Illuminate\Support\Facades\Schema::hasTable('campaigns')) {
    foreach ($replacements as $deadId => $liveUrl) {
        $n = DB::table('campaigns')
            ->where('image_url', 'like', '%'.$deadId.'%')
            ->update(['image_url' => $liveUrl, 'updated_at' => now()]);
        if ($n) {
            echo "campaigns {$deadId}: {$n}\n";
            $updated += $n;
        }
    }
}

if (Illuminate\Support\Facades\Schema::hasTable('banners')) {
    foreach ($replacements as $deadId => $liveUrl) {
        $n = DB::table('banners')
            ->where('image_url', 'like', '%'.$deadId.'%')
            ->update(['image_url' => $liveUrl, 'updated_at' => now()]);
        if ($n) {
            echo "banners {$deadId}: {$n}\n";
            $updated += $n;
        }
    }
}

echo "TOTAL_UPDATED={$updated}\n";
