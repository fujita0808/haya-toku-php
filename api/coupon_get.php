<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

try {
    $couponId = trim((string)($_GET['couponId'] ?? ''));

    if ($couponId === '') {
        json_response([
            'ok' => false,
            'error' => 'couponId is required',
        ], 400);
    }

    $sql = <<<SQL
SELECT
    c.id,
    c.coupon_code,
    c.coupon_plan_id,
    c.used_at,
    c.issued_at,
    c.used_discount_rate,
    p.id AS plan_id,
    p.title,
    p.description,
    p.initial_discount_rate,
    p.min_discount_rate,
    p.discount_mode,
    p.decay_type,
    p.decay_interval_minutes,
    p.decay_step_rate,
    p.is_active
FROM coupons c
INNER JOIN coupon_plans p
    ON c.coupon_plan_id = p.id
WHERE c.coupon_code = :coupon_code
LIMIT 1
SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':coupon_code' => $couponId,
    ]);

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        json_response([
            'ok' => false,
            'error' => 'Coupon not found',
            'couponId' => $couponId,
        ], 404);
    }

    if (!(bool)$coupon['is_active']) {
        json_response([
            'ok' => false,
            'error' => 'Coupon plan is inactive',
            'couponId' => $couponId,
        ], 403);
    }

    if (!empty($coupon['used_at'])) {
        json_response([
            'ok' => true,
            'couponId' => $couponId,
            'coupon' => [
                'id' => $coupon['id'],
                'coupon_code' => $coupon['coupon_code'],
                'coupon_plan_id' => $coupon['coupon_plan_id'],
                'title' => $coupon['title'],
                'description' => $coupon['description'],
                'issued_at' => $coupon['issued_at'],
                'used_at' => $coupon['used_at'],
                'used_discount_rate' => isset($coupon['used_discount_rate']) ? (float)$coupon['used_discount_rate'] : null,
                'used_discount_percent' => isset($coupon['used_discount_rate']) ? round(((float)$coupon['used_discount_rate']) * 100, 2) : null,
                'discount_rate' => null,
                'discount_percent' => null,
                'status' => 'used',
            ],
        ]);
    }

    $discountRate = calculateCurrentDiscountRate($coupon, (string)$coupon['issued_at']);

    json_response([
        'ok' => true,
        'couponId' => $couponId,
        'coupon' => [
            'id' => $coupon['id'],
            'coupon_code' => $coupon['coupon_code'],
            'coupon_plan_id' => $coupon['coupon_plan_id'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'issued_at' => $coupon['issued_at'],
            'discount_rate' => $discountRate,
            'discount_percent' => round($discountRate * 100, 2),
            'discount_mode' => $coupon['discount_mode'],
            'decay_type' => $coupon['decay_type'],
            'decay_interval_minutes' => (int)$coupon['decay_interval_minutes'],
            'decay_step_rate' => (float)$coupon['decay_step_rate'],
            'status' => 'available',
        ],
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}