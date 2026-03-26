<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$plan = find_current_plan();

if (!$plan) {
    json_response([
        'ok' => false,
        'error' => [
            'code' => 'NO_ACTIVE_PLAN',
            'message' => '現在有効なクーポンプランがありません。',
        ],
        'app' => [
            'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
            'displayName' => HAYA_TOKU_APP_NAME,
        ],
    ], 404);
}

$now = now_tokyo();
$current = get_current_plan_discount_payload($plan, $now);
$timelineRows = build_plan_discount_timeline($plan);

$timeline = array_map(
    static function (array $row, array $plan): array {
        $startAt = (string)($plan['start_at'] ?? '');
        $date = $startAt !== ''
            ? date('Y-m-d', strtotime($startAt . ' +' . (int)$row['day'] . ' days'))
            : null;

        return [
            'day' => $row['day'],
            'date' => $date,
            'label' => $date !== null ? $date . '（' . ((int)$row['day'] + 1) . '日目）' : ((int)$row['day'] + 1) . '日目',
            'discount_rate' => $row['discount_rate'],
            'discount_percent' => $row['discount_percent'],
        ];
    },
    $timelineRows,
    array_fill(0, count($timelineRows), $plan)
);

json_response([
    'ok' => true,
    'app' => [
        'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
        'displayName' => HAYA_TOKU_APP_NAME,
    ],
    'runtime' => [
        'now' => $now,
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
        'start_at' => $plan['start_at'] ?? null,
        'end_at' => $plan['end_at'] ?? null,
        'is_issuable' => $current['is_issuable'],
    ],
    'current' => [
        'elapsed_days' => $current['elapsed_days'],
        'discount_rate' => $current['discount_rate'],
        'discount_percent' => $current['discount_percent'],
        'message' => $current['message'],
        'initial_discount_rate' => normalize_discount_rate((float)($plan['initial_discount_rate'] ?? 0)),
        'initial_discount_percent' => round(normalize_discount_rate((float)($plan['initial_discount_rate'] ?? 0)) * 100, 2),
        'min_discount_rate' => normalize_discount_rate((float)($plan['min_discount_rate'] ?? 0)),
        'min_discount_percent' => round(normalize_discount_rate((float)($plan['min_discount_rate'] ?? 0)) * 100, 2),
    ],
    'timeline' => $timeline,
]);