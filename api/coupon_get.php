<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

try {
    $couponCode = trim((string)($_GET['couponCode'] ?? $_GET['couponId'] ?? ''));

    if ($couponCode === '') {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'BAD_REQUEST',
                'message' => 'couponCode が必要です。',
            ],
        ], 400);
    }

    $sql = <<<SQL
SELECT
    c.id,
    c.coupon_code,
    c.coupon_plan_id,
    c.issued_at,
    c.used_at,
    c.used_discount_rate,
    c.created_at,
    c.updated_at,
    p.*
FROM coupons c
INNER JOIN coupon_plans p
    ON c.coupon_plan_id = p.id
WHERE c.coupon_code = :coupon_code
LIMIT 1
SQL;

    $stmt = db()->prepare($sql);
    $ok = $stmt->execute([
        ':coupon_code' => $couponCode,
    ]);

    if (!$ok) {
        throw new RuntimeException('クーポン情報の取得に失敗しました。');
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'COUPON_NOT_FOUND',
                'message' => 'クーポンが見つかりません。',
            ],
            'coupon_code' => $couponCode,
        ], 404);
    }

    $plan = decode_plan_row($row);

    if (!(bool)($plan['is_active'] ?? false)) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'PLAN_INACTIVE',
                'message' => 'クーポンプランが非公開です。',
            ],
            'coupon_code' => $couponCode,
        ], 403);
    }

    $now = now_tokyo();
    $current = get_current_plan_discount_payload($plan, $now);

    $baseCoupon = [
        'id' => $row['id'],
        'coupon_code' => $row['coupon_code'],
        'coupon_plan_id' => $row['coupon_plan_id'],
        'title' => $plan['title'] ?? '',
        'description' => $plan['description'] ?? '',
        'issued_at' => $row['issued_at'],
        'issued_date' => !empty($row['issued_at']) ? date('Y-m-d', strtotime((string)$row['issued_at'])) : null,
    ];

    if (!empty($row['used_at'])) {
        $usedRate = isset($row['used_discount_rate']) ? (float)$row['used_discount_rate'] : null;

        json_response([
            'ok' => true,
            'coupon_code' => $couponCode,
            'coupon' => $baseCoupon + [
                'used_at' => $row['used_at'],
                'used_discount_rate' => $usedRate,
                'used_discount_percent' => $usedRate !== null ? round($usedRate * 100, 2) : null,
                'discount_rate' => $usedRate,
                'discount_percent' => $usedRate !== null ? round($usedRate * 100, 2) : null,
                'status' => 'used',
                'message' => 'このクーポンは使用済みです。',
            ],
        ]);
    }

    json_response([
        'ok' => true,
        'coupon_code' => $couponCode,
        'coupon' => $baseCoupon + [
            'used_at' => null,
            'current_discount_rate' => $current['discount_rate'],
            'current_discount_percent' => $current['discount_percent'],
            'discount_rate' => $current['discount_rate'],
            'discount_percent' => $current['discount_percent'],
            'status' => 'available',
            'message' => '現在の割引率です。未使用クーポンは日ごとに割引率が変動します。',
        ],
        'public_period' => [
            'start_at' => $plan['start_at'] ?? null,
            'end_at' => $plan['end_at'] ?? null,
            'is_issuable' => $current['is_issuable'],
        ],
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage(),
        ],
    ], 500);
}