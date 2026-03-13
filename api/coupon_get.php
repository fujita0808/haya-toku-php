<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/db.php';

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $couponId = $_GET['couponId'] ?? '';

    if ($couponId === '') {
        json_out([
            'ok' => false,
            'error' => 'couponId is required'
        ], 400);
    }

    $pdo = db();

    $sql = <<<SQL
SELECT
    c.id,
    c.coupon_code,
    c.coupon_plan_id,
    c.issued_at,
    c.used_at,
    p.title,
    p.status,
    p.start_at,
    p.end_at,
    p.description,
    p.initial_discount_rate AS discount_rate,
    p.min_discount_rate,
    p.decay_interval_minutes
FROM coupons c
JOIN coupon_plans p
  ON p.id = c.coupon_plan_id
WHERE c.coupon_code = :coupon_code
LIMIT 1
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':coupon_code' => $couponId
    ]);

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        json_out([
            'ok' => false,
            'error' => 'Coupon not found',
            'couponId' => $couponId
        ], 404);
    }

    json_out([
        'ok' => true,
        'couponId' => $couponId,
        'coupon' => [
            'id' => $coupon['id'],
            'coupon_code' => $coupon['coupon_code'],
            'coupon_plan_id' => $coupon['coupon_plan_id'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'status' => $coupon['status'],
            'start_at' => $coupon['start_at'],
            'end_at' => $coupon['end_at'],
            'discount_rate' => (int)$coupon['discount_rate'],
            'min_discount_rate' => (int)$coupon['min_discount_rate'],
            'decay_interval_minutes' => (int)$coupon['decay_interval_minutes'],
            'issued_at' => $coupon['issued_at'],
            'used_at' => $coupon['used_at'],
            'is_used' => !empty($coupon['used_at'])
        ]
    ]);
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'error' => $e->getMessage()
    ], 500);
}
