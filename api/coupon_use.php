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
    c.used_at,
    c.used_discount_rate,
    p.*
FROM coupons c
INNER JOIN coupon_plans p
    ON p.id = c.coupon_plan_id
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
        ], 403);
    }

    if (!empty($row['used_at'])) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'COUPON_ALREADY_USED',
                'message' => 'このクーポンはすでに使用済みです。',
            ],
        ], 409);
    }

    $usedAt = now_tokyo();
    $current = get_current_plan_discount_payload($plan, $usedAt);

    if ($current['discount_rate'] === null) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'OUTSIDE_PUBLIC_PERIOD',
                'message' => '公開期間外のため、このクーポンは現在使用できません。',
            ],
        ], 403);
    }

    $usedRate = (float)$current['discount_rate'];

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
        ':used_discount_rate' => $usedRate,
        ':updated_at' => $usedAt,
        ':id' => $row['id'],
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

    $logSql = <<<SQL
INSERT INTO usage_logs (
    coupon_id,
    coupon_plan_id,
    used_at,
    used_discount_rate,
    created_at
) VALUES (
    :coupon_id,
    :coupon_plan_id,
    :used_at,
    :used_discount_rate,
    :created_at
)
SQL;

    $logStmt = db()->prepare($logSql);
    $logStmt->execute([
        ':coupon_id' => $row['id'],
        ':coupon_plan_id' => $row['coupon_plan_id'],
        ':used_at' => $usedAt,
        ':used_discount_rate' => $usedRate,
        ':created_at' => $usedAt,
    ]);

    json_response([
        'ok' => true,
        'message' => 'クーポンを使用済みにしました。',
        'coupon_code' => $couponCode,
        'coupon_id' => $row['id'],
        'used_discount_rate' => $usedRate,
        'used_discount_percent' => round($usedRate * 100, 2),
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