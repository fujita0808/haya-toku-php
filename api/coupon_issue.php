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

    $now = now_iso();

    $startAt = (string)($plan['start_at'] ?? '');
    $endAt = (string)($plan['end_at'] ?? '');

    if ($startAt === '' || $endAt === '') {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'PLAN_PERIOD_MISSING',
                'message' => 'クーポンプランの公開開始日時または公開終了日時が未設定です。',
            ],
        ], 500);
    }

    $isActive = (bool)($plan['is_active'] ?? false);
    if (!$isActive) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'PLAN_INACTIVE',
                'message' => 'このクーポンプランは現在非公開です。',
            ],
        ], 403);
    }

    $nowDt = new DateTimeImmutable($now);
    $startDt = new DateTimeImmutable($startAt);
    $endDt = new DateTimeImmutable($endAt);

    if ($nowDt < $startDt || $nowDt > $endDt) {
        json_response([
            'ok' => false,
            'error' => [
                'code' => 'OUTSIDE_PUBLIC_PERIOD',
                'message' => '公開期間外のためクーポンを発行できません。',
            ],
            'plan' => [
                'id' => $plan['id'] ?? null,
                'title' => $plan['title'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
        ], 403);
    }

    $issuedDiscountRate = calculate_issue_discount_rate($plan, $now);

    $sql = <<<SQL
INSERT INTO coupons (
    coupon_code,
    coupon_plan_id,
    issued_at,
    issued_discount_rate,
    used_at,
    used_discount_rate,
    created_at,
    updated_at
) VALUES (
    :coupon_code,
    :coupon_plan_id,
    :issued_at,
    :issued_discount_rate,
    NULL,
    NULL,
    :created_at,
    :updated_at
)
RETURNING id
SQL;

    $couponId = null;
    $couponCode = null;
    $lastException = null;

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidateCode = substr(bin2hex(random_bytes(8)), 0, 8);

        try {
            $stmt = db()->prepare($sql);

            $ok = $stmt->execute([
                ':coupon_code' => $candidateCode,
                ':coupon_plan_id' => $plan['id'],
                ':issued_at' => $now,
                ':issued_discount_rate' => $issuedDiscountRate,
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
            $lastException = null;
            break;
        } catch (PDOException $e) {
            $lastException = $e;

            if ($e->getCode() === '23505') {
                continue;
            }

            throw new RuntimeException('クーポン発行中にDBエラーが発生しました。', 0, $e);
        } catch (Throwable $e) {
            $lastException = $e;
            throw $e;
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
            'discount_rate' => $issuedDiscountRate,
            'discount_percent' => round($issuedDiscountRate * 100, 2),
            'status' => 'available',
        ],
        'plan' => [
            'id' => $plan['id'],
            'title' => $plan['title'] ?? '',
            'start_at' => $startAt,
            'end_at' => $endAt,
            'initial_discount_rate' => normalize_discount_rate($plan['initial_discount_rate'] ?? 0),
            'min_discount_rate' => normalize_discount_rate($plan['min_discount_rate'] ?? 0),
            'daily_decay_rate' => round(calculate_daily_decay_rate($plan), 4),
        ],
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