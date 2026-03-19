<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

function generate_coupon_id(): string
{
    return bin2hex(random_bytes(16));
}

function generate_coupon_code(): string
{
    return substr(bin2hex(random_bytes(8)), 0, 8);
}

$plan = find_current_plan();

if (!$plan) {
    json_response([
        'ok' => false,
        'error' => 'No active coupon plan found',
    ], 404);
}

$issuedDiscountRate = calculate_discount_rate($plan);
$now = now_iso();

$id = generate_coupon_id();

$sql = <<<SQL
    INSERT INTO coupons (
        id,
        coupon_code,
        coupon_plan_id,
        user_id,
        issued_discount_rate,
        status,
        issued_at,
        created_at,
        updated_at
    ) VALUES (
        :id,
        :coupon_code,
        :coupon_plan_id,
        :user_id,
        :issued_discount_rate,
        :status,
        :issued_at,
        :created_at,
        :updated_at
    )
SQL;

$maxRetry = 5;
$attempt = 0;

while (true) {
    $couponCode = generate_coupon_code();

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':coupon_code' => $couponCode,
            ':coupon_plan_id' => (string)$plan['id'],
            ':user_id' => null,
            ':issued_discount_rate' => $issuedDiscountRate,
            ':status' => 'issued',
            ':issued_at' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        break;
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            $attempt++;
            if ($attempt >= $maxRetry) {
                throw new RuntimeException('Failed to generate unique coupon code');
            }
            continue;
        }
        throw $e;
    }
}

json_response([
    'ok' => true,
    'couponId' => $couponCode,
    'coupon' => [
        'id' => $id,
        'coupon_code' => $couponCode,
        'coupon_plan_id' => $plan['id'],
        'title' => $plan['title'] ?? null,
        'description' => $plan['description'] ?? null,
        'discount_rate' => $issuedDiscountRate,
        'discount_percent' => round($issuedDiscountRate * 100, 2),
        'issued_at' => $now,
    ],
]);