<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

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
        ], 404);
    }

    if (!(bool)$coupon['is_active']) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'PLAN_INACTIVE',
                'message' => 'クーポンプランが非公開です。',
            ],
        ], 403);
    }

    if (!empty($coupon['used_at'])) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'COUPON_ALREADY_USED',
                'message' => 'このクーポンはすでに使用済みです。',
            ],
        ], 409);
    }

    $issuedRate = isset($coupon['issued_discount_rate'])
        ? (float)$coupon['issued_discount_rate']
        : null;

    if ($issuedRate === null) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'ISSUED_RATE_MISSING',
                'message' => '発行時割引率が見つかりません。',
            ],
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
    $ok = $updateStmt->execute([
        ':used_at' => $usedAt,
        ':used_discount_rate' => $issuedRate,
        ':updated_at' => $usedAt,
        ':id' => $coupon['id'],
    ]);

    if (!$ok) {
        throw new RuntimeException('クーポン使用処理の更新に失敗しました。');
    }

    if ($updateStmt->rowCount() === 0) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'COUPON_ALREADY_USED',
                'message' => 'このクーポンはすでに使用済みです。',
            ],
        ], 409);
    }

    json_response([
        'ok' => true,
        'message' => 'クーポンを使用済みにしました。',
        'coupon_code' => $couponCode,
        'coupon_id' => $coupon['id'],
        'used_discount_rate' => $issuedRate,
        'used_discount_percent' => round($issuedRate * 100, 1),
        'used_at' => $usedAt,
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