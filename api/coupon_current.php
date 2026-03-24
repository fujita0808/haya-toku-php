<?php

declare(strict_types=1);

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

$now = now_iso();
$startAt = (string)($plan['start_at'] ?? '');
$endAt = (string)($plan['end_at'] ?? '');

$timeline = generate_discount_timeline($plan);
$currentRate = calculate_issue_discount_rate($plan, $now);
$dailyDecayRate = calculate_daily_decay_rate($plan);

$isIssuable = true;
if ($startAt !== '' && $endAt !== '') {
    $nowDt = new DateTimeImmutable($now, new DateTimeZone('Asia/Tokyo'));
    $startDt = new DateTimeImmutable($startAt, new DateTimeZone('Asia/Tokyo'));
    $endDt = new DateTimeImmutable($endAt, new DateTimeZone('Asia/Tokyo'));
    $isIssuable = !($nowDt < $startDt || $nowDt > $endDt);
}

$currentDate = (new DateTimeImmutable($now, new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
$elapsedDays = 0;
if ($startAt !== '') {
    $elapsedDays = calculate_elapsed_days_from_plan_start($startAt, $now);
}

json_response([
    'ok' => true,
    'app' => [
        'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
        'displayName' => HAYA_TOKU_APP_NAME,
    ],
    'runtime' => [
        'now' => $now,
        'current_date' => $currentDate,
        'timezone' => 'Asia/Tokyo',
    ],
    'coupon' => [
        'plan_id' => $plan['id'] ?? null,
        'title' => $plan['title'] ?? '',
        'description' => $plan['description'] ?? '',
        'product_name' => $plan['product_name'] ?? '',
        'rules' => is_array($plan['rules'] ?? null) ? $plan['rules'] : [],
        'notes' => $plan['notes'] ?? '',
        'is_active' => (bool)($plan['is_active'] ?? false),
    ],
    'public_period' => [
        'start_at' => $startAt,
        'end_at' => $endAt,
        'is_issuable' => $isIssuable,
    ],
    'current' => [
        'elapsed_days' => $elapsedDays,
        'discount_rate' => $currentRate,
        'discount_percent' => round($currentRate * 100, 2),
        'initial_discount_rate' => normalize_discount_rate($plan['initial_discount_rate'] ?? 0),
        'initial_discount_percent' => round(normalize_discount_rate($plan['initial_discount_rate'] ?? 0) * 100, 2),
        'min_discount_rate' => normalize_discount_rate($plan['min_discount_rate'] ?? 0),
        'min_discount_percent' => round(normalize_discount_rate($plan['min_discount_rate'] ?? 0) * 100, 2),
        'daily_decay_rate' => round($dailyDecayRate, 4),
        'daily_decay_percent' => round($dailyDecayRate * 100, 4),
    ],
    'timeline' => $timeline,
]);