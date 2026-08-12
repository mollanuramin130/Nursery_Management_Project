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

$login = req('POST', "$base/auth/login", [
    'email' => 'asha@example.com',
    'password' => 'Secret@123',
    'device' => ['platform' => 'web'],
]);
$token = $login['json']['data']['access_token'] ?? '';
ok('Login', $token !== '');
$auth = ["Authorization: Bearer $token"];

// Clear any pending unpaid orders that would block place.
$orders = req('GET', "$base/orders?status=PENDING_PAYMENT", null, $auth);
foreach ($orders['json']['data'] ?? [] as $row) {
    if (! empty($row['id'])) {
        req('POST', "$base/orders/{$row['id']}/cancel", ['reason' => 'phase_f_smoke cleanup'], $auth);
    }
}

$products = req('GET', "$base/products?per_page=1");
$pid = (int) (($products['json']['data'][0]['id'] ?? 0));
ok('Product', $pid > 0, 'id='.$pid);

req('DELETE', "$base/cart", null, $auth);
$add = req('POST', "$base/cart/items", ['product_id' => $pid, 'quantity' => 1], $auth);
ok('Add cart', ($add['json']['success'] ?? false) === true);

$addrs = req('GET', "$base/customer/addresses", null, $auth);
$addressId = (int) (($addrs['json']['data'][0]['id'] ?? 0));
ok('Address', $addressId > 0);

$ship = req('GET', "$base/shipping/methods");
$shipId = (int) (($ship['json']['data'][0]['id'] ?? 0));
ok('Shipping', $shipId > 0);

$preview = req('POST', "$base/checkout/preview", [
    'address_id' => $addressId,
    'shipping_method_id' => $shipId,
], $auth);
$previewTotal = (float) ($preview['json']['data']['grand_total'] ?? 0);
ok('Preview', ($preview['json']['success'] ?? false) && $previewTotal > 0, 'total='.$previewTotal);

// Online path with local stub verify
$place = req('POST', "$base/orders", [
    'address_id' => $addressId,
    'shipping_method_id' => $shipId,
    'payment_method' => 'razorpay',
], array_merge($auth, ['X-Request-Id: smoke_rzp_'.bin2hex(random_bytes(4))]));
$orderId = (int) ($place['json']['data']['id'] ?? 0);
$orderTotal = (float) ($place['json']['data']['grand_total'] ?? 0);
ok('Place razorpay order', ($place['json']['success'] ?? false) && $orderId > 0, 'id='.$orderId);

$cartAfterPlace = req('GET', "$base/cart", null, $auth);
$cartCount = (int) ($cartAfterPlace['json']['data']['item_count'] ?? 0);
ok('Cart retained after unpaid place', $cartCount >= 1, 'count='.$cartCount);

$init = req('POST', "$base/payments/initiate", ['order_id' => $orderId, 'method' => 'razorpay'], $auth);
$paymentId = (int) ($init['json']['data']['payment_id'] ?? 0);
$providerOrderId = (string) ($init['json']['data']['provider_order_id'] ?? '');
$initAmount = (float) ($init['json']['data']['amount'] ?? 0);
ok('Initiate amount matches order', $paymentId > 0 && abs($initAmount - $orderTotal) < 0.01, 'pay='.$paymentId.' amt='.$initAmount);

$payId = 'local_smoke_'.$paymentId;
$sig = 'local_'.$providerOrderId;
$verify = req('POST', "$base/payments/verify", [
    'payment_id' => $paymentId,
    'provider_order_id' => $providerOrderId,
    'provider_payment_id' => $payId,
    'provider_signature' => $sig,
], $auth);
ok(
    'Verify confirms order',
    ($verify['json']['data']['payment_status'] ?? '') === 'success'
        && ($verify['json']['data']['order_status'] ?? '') === 'CONFIRMED',
    $verify['json']['message'] ?? '',
);

$cartAfterPay = req('GET', "$base/cart", null, $auth);
ok('Cart cleared after paid confirm', (int) ($cartAfterPay['json']['data']['item_count'] ?? -1) === 0);

$dup = req('POST', "$base/payments/verify", [
    'payment_id' => $paymentId,
    'provider_order_id' => $providerOrderId,
    'provider_payment_id' => $payId,
    'provider_signature' => $sig,
], $auth);
ok('Verify idempotent', ($dup['json']['success'] ?? false) === true && ($dup['json']['data']['payment_status'] ?? '') === 'success');

// COD path
req('POST', "$base/cart/items", ['product_id' => $pid, 'quantity' => 1], $auth);
$placeCod = req('POST', "$base/orders", [
    'address_id' => $addressId,
    'shipping_method_id' => $shipId,
    'payment_method' => 'cod',
], array_merge($auth, ['X-Request-Id: smoke_cod_'.bin2hex(random_bytes(4))]));
ok(
    'COD confirms immediately',
    ($placeCod['json']['data']['status'] ?? '') === 'CONFIRMED',
    'status='.($placeCod['json']['data']['status'] ?? '?'),
);
$cartCod = req('GET', "$base/cart", null, $auth);
ok('Cart cleared after COD', (int) ($cartCod['json']['data']['item_count'] ?? -1) === 0);

echo "DONE\n";
