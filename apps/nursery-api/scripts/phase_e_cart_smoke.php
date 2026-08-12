<?php

declare(strict_types=1);

$base = getenv('API_BASE') ?: 'http://127.0.0.1:8000/api/v1';

function req(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $h,
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
}

$products = req('GET', "$base/products?per_page=5");
$ids = [];
foreach (($products['json']['data'] ?? []) as $row) {
    if (is_array($row) && isset($row['id'])) {
        $ids[] = (int) $row['id'];
    }
}
$pidA = $ids[0] ?? 0;
$pidB = $ids[1] ?? $pidA;
ok('Resolve catalog products', $pidA > 0, 'a='.$pidA.' b='.$pidB);

$cartToken = 'phase-e-'.bin2hex(random_bytes(6));

$add = req('POST', "$base/cart/items", ['product_id' => $pidA, 'quantity' => 1], ["X-Cart-Token: $cartToken"]);
$ok = ($add['json']['success'] ?? false) === true;
$free = $add['json']['data']['free_delivery'] ?? null;
ok('Guest add to cart', $ok && is_array($free), 'http='.$add['code'].' free='.json_encode($free));

$login = req('POST', "$base/auth/login", [
    'email' => 'asha@example.com',
    'password' => 'Secret@123',
    'device' => ['platform' => 'web'],
], ["X-Cart-Token: $cartToken"]);
$token = $login['json']['data']['access_token'] ?? '';
ok('Login with guest cart merge', $token !== '', 'http='.$login['code']);

$auth = ["Authorization: Bearer $token"];
$cart = req('GET', "$base/cart", null, $auth);
$data = $cart['json']['data'] ?? [];
ok('Auth cart has items + free_delivery', ! empty($data['items']) && isset($data['free_delivery']['threshold']), 'count='.($data['item_count'] ?? 0));

$itemId = $data['items'][0]['id'] ?? null;
$productId = $data['items'][0]['product_id'] ?? null;

$coupon = req('POST', "$base/cart/apply-coupon", ['code' => 'WELCOME10', 'discount' => 999999], $auth);
$disc = (float) ($coupon['json']['data']['discount_total'] ?? 0);
ok('Apply coupon ignores client discount', ($coupon['json']['success'] ?? false) && $disc > 0 && $disc < 999999, 'discount='.$disc);

$price = req('POST', "$base/cart/items", ['product_id' => $pidB, 'quantity' => 1, 'price' => 1], $auth);
$unit = null;
foreach (($price['json']['data']['items'] ?? []) as $row) {
    if ((int) ($row['product_id'] ?? 0) === $pidB) {
        $unit = (float) $row['unit_price'];
    }
}
ok('Add ignores client price', ($price['json']['success'] ?? false) && $unit !== null && $unit > 1, 'unit='.(string) $unit);

if ($itemId && $productId) {
    $move = req('POST', "$base/cart/items/{$itemId}/move-to-wishlist", null, $auth);
    ok('Move cart → wishlist', ($move['json']['success'] ?? false) === true, $move['json']['message'] ?? '');

    $back = req('POST', "$base/wishlist/{$productId}/move-to-cart", ['quantity' => 1], $auth);
    ok('Move wishlist → cart', ($back['json']['success'] ?? false) === true && isset($back['json']['data']['cart']), $back['json']['message'] ?? '');
}

$stock = req('POST', "$base/cart/items", ['product_id' => $pidA, 'quantity' => 99999], $auth);
ok('Stock validation rejects oversell', ($stock['json']['success'] ?? true) === false, $stock['json']['message'] ?? '');

$clear = req('DELETE', "$base/cart", null, $auth);
ok('Clear cart', ($clear['json']['success'] ?? false) === true && (int) ($clear['json']['data']['item_count'] ?? -1) === 0, 'count='.($clear['json']['data']['item_count'] ?? '?'));

echo "DONE\n";
