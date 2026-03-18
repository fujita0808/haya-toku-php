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
    c.used_discount_rate,
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
            'ok' => false,
            'error' => 'Coupon already used',
            'couponId' => $couponId,
            'used_at' => $coupon['used_at'],
            'used_discount_rate' => $coupon['used_discount_rate'] !== null
                ? (float)$coupon['used_discount_rate']
                : null
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $now = new DateTimeImmutable('now');
    $discountRate = calculateCurrentDiscountRate($coupon, $coupon['issued_at'], $now);
    $usedAt = $now->format(DateTimeInterface::ATOM);

    $updateSql = <<<SQL
UPDATE coupons
SET
    used_at = :used_at,
    used_discount_rate = :used_discount_rate
WHERE id = :id
  AND used_at IS NULL
SQL;

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        ':used_at' => $usedAt,
        ':used_discount_rate' => $discountRate,
        ':id' => $coupon['id']
    ]);

    if ($updateStmt->rowCount() !== 1) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'error' => 'Coupon could not be used',
            'couponId' => $couponId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Coupon used successfully',
        'couponId' => $couponId,
        'coupon' => [
            'id' => $coupon['id'],
            'coupon_code' => $coupon['coupon_code'],
            'coupon_plan_id' => $coupon['coupon_plan_id'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'issued_at' => $coupon['issued_at'],
            'used_at' => $usedAt,
            'used_discount_rate' => $discountRate,
            'used_discount_percent' => round($discountRate * 100, 2),
            'status' => 'used'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
