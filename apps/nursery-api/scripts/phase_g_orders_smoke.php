<?php

declare(strict_types=1);

$base = getenv('API_BASE') ?: 'http://127.0.0.1:8000/api/v1';

function req(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = json_decode((string) $raw, true);

    return ['code' => $code, 'json' => is_array($json) ? $json : []];
}

function ok(string $label, bool $pass, string $detail = ''): void
{
    echo ($pass ? '[PASS] ' : '[FAIL] ').$label.($detail !== '' ? " — $detail" : '').PHP_EOL;
}

$login = req('POST', "$base/auth/login", [
    'email' => 'asha@example.com',
    'password' => 'Secret@123',
    'device' => ['platform' => 'web'],
]);
$token = $login['json']['data']['access_token'] ?? '';
ok('Login', $token !== '');
$auth = ["Authorization: Bearer $token"];

// Cleanup pending
$orders = req('GET', "$base/orders?status=PENDING_PAYMENT", null, $auth);
foreach ($orders['json']['data'] ?? [] as $row) {
    if (! empty($row['id'])) {
        req('POST', "$base/orders/{$row['id']}/cancel", ['reason_code' => 'changed_mind'], $auth);
    }
}

$products = req('GET', "$base/products?per_page=1");
$pid = (int) (($products['json']['data'][0]['id'] ?? 0));
req('DELETE', "$base/cart", null, $auth);
req('POST', "$base/cart/items", ['product_id' => $pid, 'quantity' => 1], $auth);
$addrs = req('GET', "$base/customer/addresses", null, $auth);
$addressId = (int) (($addrs['json']['data'][0]['id'] ?? 0));
$ship = req('GET', "$base/shipping/methods");
$shipId = (int) (($ship['json']['data'][0]['id'] ?? 0));

$place = req('POST', "$base/orders", [
    'address_id' => $addressId,
    'shipping_method_id' => $shipId,
    'payment_method' => 'cod',
], array_merge($auth, ['X-Request-Id: g_cod_'.bin2hex(random_bytes(3))]));
$orderId = (int) ($place['json']['data']['id'] ?? 0);
ok('COD order', $orderId > 0 && ($place['json']['data']['status'] ?? '') === 'CONFIRMED', 'id='.$orderId);

$list = req('GET', "$base/orders?per_page=5", null, $auth);
$first = $list['json']['data'][0] ?? [];
ok('List has flags', isset($first['can_cancel'], $first['can_reorder']), json_encode([
    'can_cancel' => $first['can_cancel'] ?? null,
    'can_reorder' => $first['can_reorder'] ?? null,
]));

$detail = req('GET', "$base/orders/$orderId", null, $auth);
ok('Detail actions', ($detail['json']['data']['actions']['can_cancel'] ?? false) === true);

$track = req('GET', "$base/orders/$orderId/tracking", null, $auth);
ok('Tracking timeline', is_array($track['json']['data']['timeline'] ?? null), 'steps='.count($track['json']['data']['timeline'] ?? []));

$reorder = req('POST', "$base/orders/$orderId/reorder", [], $auth);
ok('Reorder adds cart', ($reorder['json']['success'] ?? false) === true && ! empty($reorder['json']['data']['cart']['items']));
ok('Reorder summary', isset($reorder['json']['data']['reorder_summary']['added']));

$cancel = req('POST', "$base/orders/$orderId/cancel", [
    'reason_code' => 'changed_mind',
], $auth);
ok('Cancel confirmed order', ($cancel['json']['data']['status'] ?? '') === 'CANCELLED', 'pay='.($cancel['json']['data']['payment_status'] ?? '?'));

$cancelAgain = req('POST', "$base/orders/$orderId/cancel", ['reason_code' => 'changed_mind'], $auth);
ok('Double cancel rejected', ($cancelAgain['json']['success'] ?? true) === false);

// Ownership: login as second user if exists - skip soft
$other = req('POST', "$base/auth/login", [
    'email' => 'ravi@example.com',
    'password' => 'Secret@123',
    'device' => ['platform' => 'web'],
]);
$otherToken = $other['json']['data']['access_token'] ?? '';
if ($otherToken) {
    $idor = req('GET', "$base/orders/$orderId", null, ["Authorization: Bearer $otherToken"]);
    ok('IDOR blocked', ($idor['json']['success'] ?? true) === false || ($idor['code'] ?? 0) === 404, 'code='.$idor['code']);
} else {
    ok('IDOR skipped (no second user)', true);
}

echo "DONE\n";
