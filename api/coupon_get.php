<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (function_exists('send_api_headers')) {
        send_api_headers();
    }
    http_response_code(200);
    exit;
}

if (function_exists('send_api_headers')) {
    send_api_headers();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('METHOD_NOT_ALLOWED', 'GET メソッドでアクセスしてください。', 405);
}

$couponId = trim((string)($_GET['coupon_id'] ?? ''));
$couponCode = trim((string)($_GET['coupon_code'] ?? ''));

if ($couponId === '' && $couponCode === '') {
    api_error('COUPON_IDENTIFIER_REQUIRED', 'coupon_id または coupon_code が必要です。', 400);
}

$coupon = null;

if ($couponId !== '') {
    $coupon = find_coupon_by_id($couponId);
} elseif ($couponCode !== '') {
    $coupon = find_coupon_by_code($couponCode);
}

if (!is_array($coupon)) {
    api_error('COUPON_NOT_FOUND', '対象クーポンが見つかりません。', 404);
}

$planId = trim((string)($coupon['coupon_plan_id'] ?? $coupon['plan_id'] ?? ''));
$plan = $planId !== '' ? find_plan_by_id($planId) : null;

if ($plan === null) {
    api_error('PLAN_NOT_FOUND', '対応するクーポンプランが見つかりません。', 404, [
        'coupon' => [
            'id' => (string)($coupon['id'] ?? ''),
            'coupon_code' => (string)($coupon['coupon_code'] ?? ''),
            'coupon_plan_id' => $planId,
        ],
    ]);
}

$viewModel = build_plan_view_model($plan);
$schedule = $viewModel['schedule'] ?? [];

$denyReason = coupon_use_denied_reason($coupon, $plan);
$isUsable = ($denyReason === 'ok');

api_success([
    'coupon' => [
        'id' => (string)($coupon['id'] ?? ''),
        'coupon_code' => (string)($coupon['coupon_code'] ?? ''),
        'coupon_plan_id' => $planId,
        'issued_at' => (string)($coupon['issued_at'] ?? ''),
        'used_at' => (string)($coupon['used_at'] ?? ''),
        'issued_discount_rate' => (float)($coupon['issued_discount_rate'] ?? 0),
        'used_discount_rate' => (float)($coupon['used_discount_rate'] ?? 0),
        'is_used' => coupon_is_used($coupon),
    ],
    'plan' => [
        'id' => (string)$viewModel['id'],
        'title' => (string)$viewModel['title'],
        'description' => (string)$viewModel['description'],
        'product_name' => (string)$viewModel['product_name'],
        'status_code' => (string)$viewModel['status_code'],
        'status_label' => (string)$viewModel['status_label'],
        'is_active' => (bool)$viewModel['is_active'],
        'start_at' => (string)$viewModel['start_at'],
        'end_at' => (string)$viewModel['end_at'],
        'initial_discount_rate' => (float)$viewModel['initial_discount_rate'],
        'min_discount_rate' => (float)$viewModel['min_discount_rate'],
        'rules' => is_array($viewModel['rules']) ? $viewModel['rules'] : [],
        'notes' => (string)$viewModel['notes'],
    ],
    'schedule' => [
        'status' => (string)($schedule['status'] ?? ''),
        'is_active_now' => (bool)($schedule['is_active_now'] ?? false),
        'current_discount_rate' => (float)($schedule['current_discount_rate'] ?? 0),
        'initial_discount_rate' => (float)($schedule['initial_discount_rate'] ?? 0),
        'min_discount_rate' => (float)($schedule['min_discount_rate'] ?? 0),
        'total_days' => (int)($schedule['total_days'] ?? 0),
        'elapsed_days' => (int)($schedule['elapsed_days'] ?? 0),
        'remaining_days' => (int)($schedule['remaining_days'] ?? 0),
        'progress_ratio' => (float)($schedule['progress_ratio'] ?? 0),
        'next_change_at' => $schedule['next_change_at'] ?? null,
    ],
    'availability' => [
        'is_usable' => $isUsable,
        'reason_code' => $denyReason,
        'reason_message' => coupon_use_denied_message($denyReason),
    ],
], 200);