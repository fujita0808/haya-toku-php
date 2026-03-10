<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (request_method() !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method Not Allowed'], 405);
}

$plan = find_current_plan();
if (!$plan) {
    json_response(['ok' => false, 'message' => '有効なクーポンがありません。'], 404);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
$displayName = trim((string)($input['displayName'] ?? 'guest'));
$userId = trim((string)($input['userId'] ?? 'browser-guest'));
$currentRate = calculate_discount_rate($plan);

$log = [
    'id' => 'use_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)),
    'coupon_plan_id' => $plan['id'],
    'user_id' => $userId,
    'display_name' => $displayName,
    'used_at' => now_iso(),
    'discount_rate' => round($currentRate, 4),
    'discounted_price' => (int)round($plan['unit_price'] * (1 - $currentRate)),
];

append_usage_log($log);

json_response([
    'ok' => true,
    'message' => 'クーポン利用を記録しました。',
    'used' => $log,
]);
