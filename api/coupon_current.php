<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$plan = find_current_plan();
if (!$plan) {
    json_response([
        'ok' => false,
        'message' => '現在有効なクーポンはありません。',
        'app' => [
            'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
            'displayName' => HAYA_TOKU_APP_NAME,
        ],
    ], 404);
}

$rate = calculate_discount_rate($plan);
$timeline = generate_discount_timeline($plan);

$baseAt = !empty($plan['created_at']) ? (string)$plan['created_at'] : now_iso();
$intervalMinutes = isset($plan['decay_interval_minutes']) ? (int)$plan['decay_interval_minutes'] : 0;

$deadlineText = null;
if ($intervalMinutes > 0 && !empty($timeline)) {
    $lastTimeline = end($timeline);
    if (!empty($lastTimeline['at'])) {
        $deadlineText = date('H:i', strtotime((string)$lastTimeline['at'])) . ' まで';
    }
}

json_response([
    'ok' => true,
    'app' => [
        'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
        'displayName' => HAYA_TOKU_APP_NAME,
    ],
    'runtime' => [
        'liffId' => '2009179515-rJ1LaY8l',
        'useConfirmSeconds' => 10,
        'cancelPolicy' => 'confirmInClient',
    ],
    'coupon' => [
        'planId' => $plan['id'],
        'title' => $plan['title'] ?? '',
        'description' => $plan['description'] ?? '',
        'status' => !empty($plan['is_active']) ? '公開中' : '非公開',
        'deadlineText' => $deadlineText,
        'rules' => $plan['rules'] ?? [],
        'notes' => $plan['notes'] ?? '',
        'productName' => $plan['product_name'] ?? '',
        'unitPrice' => (int)($plan['unit_price'] ?? 0),
        'targetRevenue' => (int)($plan['target_revenue'] ?? 0),
    ],
    'current' => [
        'now' => now_iso(),
        'discountRate' => round($rate * 100, 1),
        'discountRateRaw' => $rate,
        'discountedPrice' => (int)round(((int)($plan['unit_price'] ?? 0)) * (1 - $rate)),
        'baseAt' => date(DATE_ATOM, strtotime($baseAt)),
        'decayIntervalMinutes' => $intervalMinutes,
    ],
    'timeline' => $timeline,
]);
