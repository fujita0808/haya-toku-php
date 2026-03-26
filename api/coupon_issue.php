<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

try {
    $plan = find_current_plan();

    if (!$plan) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'NO_ACTIVE_PLAN',
                'message' => '現在有効なクーポンプランがありません。',
            ],
        ], 404);
    }

    $now = now_tokyo();
    $current = get_current_plan_discount_payload($plan, $now);

    if (!(bool)($plan['is_active'] ?? false)) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'PLAN_INACTIVE',
                'message' => 'このクーポンプランは現在非公開です。',
            ],
        ], 403);
    }

    if (!$current['is_issuable']) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'OUTSIDE_PUBLIC_PERIOD',
                'message' => '公開期間外のためクーポンを発行できません。',
            ],
            'plan' => [
                'id' => $plan['id'] ?? null,
                'title' => $plan['title'] ?? '',
                'start_at' => $plan['start_at'] ?? null,
                'end_at' => $plan['end_at'] ?? null,
            ],
        ], 403);
    }

    $sql = <<<SQL
INSERT INTO coupons (
    coupon_code,
    coupon_plan_id,
    issued_at,
    used_at,
    used_discount_rate,
    created_at,
    updated_at
) VALUES (
    :coupon_code,
    :coupon_plan_id,
    :issued_at,
    NULL,
    NULL,
    :created_at,
    :updated_at
)
RETURNING id
SQL;

    $couponId = null;
    $couponCode = null;

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidateCode = substr(bin2hex(random_bytes(8)), 0, 8);

        try {
            $stmt = db()->prepare($sql);
            $ok = $stmt->execute([
                ':coupon_code' => $candidateCode,
                ':coupon_plan_id' => $plan['id'],
                ':issued_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            if (!$ok) {
                throw new RuntimeException('クーポン発行の保存に失敗しました。');
            }

            $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
            $insertedId = $inserted['id'] ?? null;

            if ($insertedId === null || $insertedId === '') {
                throw new RuntimeException('発行後のクーポンID取得に失敗しました。');
            }

            $couponId = $insertedId;
            $couponCode = $candidateCode;
            break;
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                continue;
            }

            throw new RuntimeException('クーポン発行中にDBエラーが発生しました。', 0, $e);
        }
    }

    if ($couponId === null || $couponCode === null) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'COUPON_CODE_GENERATION_FAILED',
                'message' => 'クーポンコードの生成に複数回失敗しました。時間をおいて再度お試しください。',
            ],
        ], 500);
    }

    json_response([
        'ok' => true,
        'message' => 'クーポンを発行しました。',
        'coupon' => [
            'id' => $couponId,
            'coupon_code' => $couponCode,
            'coupon_plan_id' => $plan['id'],
            'title' => $plan['title'] ?? '',
            'description' => $plan['description'] ?? '',
            'issued_at' => $now,
            'issued_date' => date('Y-m-d', strtotime($now)),
            'status' => 'available',
            'current_discount_rate' => $current['discount_rate'],
            'current_discount_percent' => $current['discount_percent'],
        ],
        'plan' => [
            'id' => $plan['id'],
            'title' => $plan['title'] ?? '',
            'start_at' => $plan['start_at'] ?? null,
            'end_at' => $plan['end_at'] ?? null,
        ],
        'note' => '未使用クーポンの割引率は固定ではありません。使用時点の割引率が適用されます。',
    ], 201);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage(),
        ],
    ], 500);
}