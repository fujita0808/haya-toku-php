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
    c.issued_discount_rate,
    c.used_at,
    c.used_discount_rate,
    p.id AS plan_id,
    p.title,
    p.description,
    p.is_active
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

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'COUPON_NOT_FOUND',
                'message' => 'クーポンが見つかりません。',
            ],
            'coupon_code' => $couponCode,
        ], 404);
    }

    if (!(bool)$coupon['is_active']) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'PLAN_INACTIVE',
                'message' => 'クーポンプランが非公開です。',
            ],
            'coupon_code' => $couponCode,
        ], 403);
    }

    $issuedDiscountRate = isset($coupon['issued_discount_rate'])
        ? (float)$coupon['issued_discount_rate']
        : null;

    $elapsedDays = calculateElapsedDaysByDate((string)$coupon['issued_at']);

    if (!empty($coupon['used_at'])) {
        json_response([
            'ok' => true,
            'coupon_code' => $couponCode,
            'coupon' => [
                'id' => $coupon['id'],
                'coupon_code' => $coupon['coupon_code'],
                'coupon_plan_id' => $coupon['coupon_plan_id'],
                'title' => $coupon['title'],
                'description' => $coupon['description'],
                'issued_at' => $coupon['issued_at'],
                'issued_date' => date('Y-m-d', strtotime((string)$coupon['issued_at'])),
                'elapsed_days' => $elapsedDays,
                'issued_discount_rate' => $issuedDiscountRate,
                'issued_discount_percent' => $issuedDiscountRate !== null ? round($issuedDiscountRate * 100, 2) : null,
                'used_at' => $coupon['used_at'],
                'used_discount_rate' => isset($coupon['used_discount_rate']) ? (float)$coupon['used_discount_rate'] : null,
                'used_discount_percent' => isset($coupon['used_discount_rate']) ? round(((float)$coupon['used_discount_rate']) * 100, 2) : null,
                'discount_rate' => $issuedDiscountRate,
                'discount_percent' => $issuedDiscountRate !== null ? round($issuedDiscountRate * 100, 2) : null,
                'status' => 'used',
            ],
        ]);
    }

    json_response([
        'ok' => true,
        'coupon_code' => $couponCode,
        'coupon' => [
            'id' => $coupon['id'],
            'coupon_code' => $coupon['coupon_code'],
            'coupon_plan_id' => $coupon['coupon_plan_id'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'issued_at' => $coupon['issued_at'],
            'issued_date' => date('Y-m-d', strtotime((string)$coupon['issued_at'])),
            'elapsed_days' => $elapsedDays,
            'issued_discount_rate' => $issuedDiscountRate,
            'issued_discount_percent' => $issuedDiscountRate !== null ? round($issuedDiscountRate * 100, 2) : null,
            'discount_rate' => $issuedDiscountRate,
            'discount_percent' => $issuedDiscountRate !== null ? round($issuedDiscountRate * 100, 2) : null,
            'status' => 'available',
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