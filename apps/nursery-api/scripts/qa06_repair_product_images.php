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
    '1466692476866-aef1dfb1e735' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000',
    '1593482892290-f54927ae2b7a' => 'https://images.unsplash.com/photo-1565626929866-e11c64e607cf?w=1000',
    '1593691509543-c55fb32e7356' => 'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000',
    '1463936575829-25148e1670d9' => 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000',
    '1470058869958-2a77ade41aa8' => 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?w=1000',
    '1512428813834-c702c6dc18c9' => 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000',
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
