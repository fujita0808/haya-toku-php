<?php

declare(strict_types=1);

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
            c.issued_at,
            c.issued_discount_rate,
            c.used_at,
            c.used_discount_rate,
            p.title,
            p.description,
            p.is_active
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

    if (!(bool)$coupon['is_active']) {
        json_response([
            'ok' => false,
            'error' => 'Coupon plan is inactive',
        ], 403);
    }

    if (!empty($coupon['used_at'])) {
        json_response([
            'ok' => false,
            'error' => 'Coupon already used',
        ], 409);
    }

    $issuedRate = isset($coupon['issued_discount_rate'])
        ? (float)$coupon['issued_discount_rate']
        : null;

    if ($issuedRate === null) {
        json_response([
            'ok' => false,
            'error' => 'Issued discount rate is missing',
        ], 500);
    }

    $usedAt = now_iso();

    $updateSql = <<<SQL
        UPDATE coupons
        SET
            used_at = :used_at,
            used_discount_rate = :used_discount_rate,
            updated_at = :updated_at
        WHERE id = :id
          AND used_at IS NULL
    SQL;

    $updateStmt = db()->prepare($updateSql);
    $updateStmt->execute([
        ':used_at' => $usedAt,
        ':used_discount_rate' => $issuedRate,
        ':updated_at' => $usedAt,
        ':id' => $coupon['id'],
    ]);

    if ($updateStmt->rowCount() === 0) {
        json_response([
            'ok' => false,
            'error' => 'Coupon already used',
        ], 409);
    }

    json_response([
        'ok' => true,
        'message' => 'Coupon used successfully',
        'couponId' => $couponId,
        'coupon_id' => $coupon['id'],
        'used_discount_rate' => $issuedRate,
        'used_discount_percent' => round($issuedRate * 100, 1),
        'used_at' => $usedAt,
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}