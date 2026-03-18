<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/coupon_discount.php';

try {
    $couponId = $_GET['couponId'] ?? '';

    if ($couponId === '') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'couponId is required'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $pdo = db();

    $sql = <<<SQL
SELECT
    c.id,
    c.coupon_code,
    c.coupon_plan_id,
    c.title,
    c.description,
    c.used_at,
    c.issued_at,
    p.id AS plan_id,
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

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':coupon_code' => $couponId
    ]);

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'error' => 'Coupon not found',
            'couponId' => $couponId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!(bool)$coupon['is_active']) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Coupon plan is inactive',
            'couponId' => $couponId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!empty($coupon['used_at'])) {
        echo json_encode([
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
                'discount_rate' => null,
                'status' => 'used'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $discountRate = calculateCurrentDiscountRate($coupon, $coupon['issued_at']);

    echo json_encode([
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
            'status' => 'available'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
