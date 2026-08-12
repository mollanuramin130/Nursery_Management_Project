<?php

declare(strict_types=1);

$base = getenv('API_BASE') ?: 'http://127.0.0.1:8000/api/v1';

function req(string $method, string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = json_decode((string) $raw, true);

    return ['code' => $code, 'json' => is_array($json) ? $json : [], 'raw' => (string) $raw];
}

function ok(string $label, bool $pass, string $detail = ''): void
{
    echo ($pass ? '[PASS] ' : '[FAIL] ').$label.($detail !== '' ? " — $detail" : '').PHP_EOL;
    if (! $pass) {
        $GLOBALS['failed'] = true;
    }
}

$failed = false;

$offers = req('GET', "$base/offers");
ok('GET /offers', ($offers['json']['success'] ?? false) === true && isset($offers['json']['data']['sale_products']));
$saleN = count($offers['json']['data']['sale_products'] ?? []);
ok('Offers include sale products', $saleN > 0, "n=$saleN");

$featured = req('GET', "$base/campaigns/featured");
ok('GET /campaigns/featured', ($featured['json']['success'] ?? false) === true);
$featN = count($featured['json']['data'] ?? []);
ok('Featured campaigns', $featN > 0, "n=$featN");

$active = req('GET', "$base/campaigns?status=active");
ok('GET /campaigns active', ($active['json']['success'] ?? false) === true);
foreach ($active['json']['data'] ?? [] as $c) {
    if (($c['status'] ?? '') !== 'active') {
        ok('Active list status', false, ($c['slug'] ?? '?').'='.($c['status'] ?? ''));
        break;
    }
}
ok('Active campaigns status=active', true);

$upcoming = req('GET', "$base/campaigns?status=upcoming");
ok('GET /campaigns upcoming', ($upcoming['json']['success'] ?? false) === true);

$slug = $featured['json']['data'][0]['slug'] ?? 'monsoon-plants-2026';
$detail = req('GET', "$base/campaigns/$slug");
ok('Campaign detail', ($detail['json']['success'] ?? false) === true, $slug);
ok('Campaign has products', count($detail['json']['data']['products'] ?? []) > 0);

$products = req('GET', "$base/campaigns/$slug/products");
ok('Campaign products endpoint', ($products['json']['success'] ?? false) === true);

$sale = req('GET', "$base/products?on_sale=1&per_page=5");
ok('Products on_sale filter', ($sale['json']['success'] ?? false) === true && count($sale['json']['data'] ?? []) > 0);

$opts = req('GET', "$base/plant-finder/options");
ok('Finder options', ($opts['json']['success'] ?? false) === true);

$body = [
    'location' => 'indoor',
    'sunlight' => 'bright_indirect',
    'watering' => 'weekly',
    'experience' => 'beginner',
    'purpose' => ['low_maintenance', 'air_purifying'],
];
$match1 = req('POST', "$base/plant-finder/match", $body);
ok('Finder match', ($match1['json']['success'] ?? false) === true);
$r1 = $match1['json']['data']['results'] ?? [];
ok('Finder results', count($r1) > 0, 'n='.count($r1));
ok('Match score present', isset($r1[0]['match_score']) && isset($r1[0]['match_reasons']));

$match2 = req('POST', "$base/plant-finder/match", $body);
$r2 = $match2['json']['data']['results'] ?? [];
$ids1 = array_map(fn ($x) => $x['product']['id'] ?? null, $r1);
$ids2 = array_map(fn ($x) => $x['product']['id'] ?? null, $r2);
ok('Finder deterministic', $ids1 === $ids2);

$bad = req('POST', "$base/plant-finder/match", ['location' => 'spaceship']);
ok('Finder validation', ($bad['json']['success'] ?? true) === false && $bad['code'] === 422);

$missing = req('GET', "$base/campaigns/does-not-exist-xyz");
ok('Campaign 404', $missing['code'] === 404);

echo ($failed ? 'DONE WITH FAILURES' : 'DONE').PHP_EOL;
exit($failed ? 1 : 0);
