<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$couponId = trim((string)($_GET['couponId'] ?? ''));
if ($couponId === '') {
    json_response([
        'ok' => false,
        'error' => 'couponId is required',
    ], 400);
}

try {
    $sql = <<<SQL
        SELECT
            c.id,
            c.coupon_code,
            c.coupon_plan_id,
            p.title,
            p.description,
            c.issued_at,
            c.used_at,
            c.used_discount_rate,
            p.initial_discount_rate,
            p.min_discount_rate,
            p.discount_mode,
            p.decay_type,
            p.decay_interval_minutes,
            p.decay_step_rate
        FROM coupons c
        JOIN coupon_plans p ON p.id = c.coupon_plan_id
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
        ], 404);
    }

    if (!empty($coupon['used_at'])) {
        json_response([
            'ok' => false,
            'error' => 'Coupon already used',
        ], 409);
    }

    $plan = [
        'initial_discount_rate' => (float)$coupon['initial_discount_rate'],
        'min_discount_rate' => (float)$coupon['min_discount_rate'],
        'discount_mode' => $coupon['discount_mode'],
        'decay_type' => $coupon['decay_type'],
        'decay_interval_minutes' => (int)$coupon['decay_interval_minutes'],
        'decay_step_rate' => (float)$coupon['decay_step_rate'],
    ];

    $issuedAt = (string)$coupon['issued_at'];
    $usedRate = calculateCurrentDiscountRate($plan, $issuedAt);

    $updateSql = <<<SQL
        UPDATE coupons
        SET
            used_at = NOW(),
            used_discount_rate = :used_discount_rate
        WHERE id = :id
    SQL;

    $updateStmt = db()->prepare($updateSql);
    $updateStmt->execute([
        ':used_discount_rate' => $usedRate,
        ':id' => $coupon['id'],
    ]);

    json_response([
        'ok' => true,
        'message' => 'Coupon used successfully',
        'couponId' => $couponId,
        'coupon_id' => $coupon['id'],
        'used_discount_rate' => $usedRate,
        'used_discount_percent' => round($usedRate * 100, 1),
        'used_at' => now_iso(),
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}
