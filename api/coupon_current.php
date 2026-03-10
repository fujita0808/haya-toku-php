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
        'title' => $plan['title'],
        'description' => $plan['description'],
        'status' => '公開中',
        'deadlineText' => date('H:i', strtotime($plan['end_at'])) . ' まで',
        'rules' => $plan['rules'],
        'notes' => $plan['notes'],
        'productName' => $plan['product_name'],
        'unitPrice' => $plan['unit_price'],
        'targetRevenue' => $plan['target_revenue'],
    ],
    'current' => [
        'now' => now_iso(),
        'discountRate' => round($rate * 100, 1),
        'discountRateRaw' => $rate,
        'discountedPrice' => (int)round($plan['unit_price'] * (1 - $rate)),
        'startAt' => date(DATE_ATOM, strtotime($plan['start_at'])),
        'endAt' => date(DATE_ATOM, strtotime($plan['end_at'])),
    ],
    'timeline' => $timeline,
]);
