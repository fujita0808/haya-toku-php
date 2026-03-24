<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

try {
    $plan = find_current_plan();

    if (!$plan) {
        json_response([
            'ok' => false,
            'error' => 'No active coupon plan found',
        ], 404);
    }

    $now = now_iso();

    $startAt = (string)($plan['start_at'] ?? '');
    $endAt = (string)($plan['end_at'] ?? '');

    if ($startAt === '' || $endAt === '') {
        json_response([
            'ok' => false,
            'error' => 'Plan start_at / end_at is missing',
        ], 500);
    }

    $nowDt = new DateTimeImmutable($now, new DateTimeZone('Asia/Tokyo'));
    $startDt = new DateTimeImmutable($startAt, new DateTimeZone('Asia/Tokyo'));
    $endDt = new DateTimeImmutable($endAt, new DateTimeZone('Asia/Tokyo'));

    if ($nowDt < $startDt || $nowDt > $endDt) {
        json_response([
            'ok' => false,
            'error' => 'Coupon cannot be issued outside the public period',
            'plan' => [
                'id' => $plan['id'] ?? null,
                'title' => $plan['title'] ?? null,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
        ], 403);
    }

    $issuedDiscountRate = calculate_issue_discount_rate($plan, $now);
    $couponCode = substr(bin2hex(random_bytes(8)), 0, 8);

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

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':coupon_code' => $couponCode,
        ':coupon_plan_id' => $plan['id'],
        ':issued_at' => $now,
        ':issued_discount_rate' => $issuedDiscountRate,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
    $couponId = $inserted['id'] ?? null;

    json_response([
        'ok' => true,
        'message' => 'Coupon issued successfully',
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
        'error' => $e->getMessage(),
    ], 500);
}