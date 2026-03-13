<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/api_headers.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();

/**
 * 現在有効なクーポンプランを1件取得
 * ひとまず status='active' を有効とみなす
 */
$stmt = $db->query("
  SELECT *
  FROM coupon_plans
  WHERE status = 'active'
    AND start_at <= NOW()
    AND end_at >= NOW()
  ORDER BY start_at DESC
  LIMIT 1
");

$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    echo json_encode([
        'ok' => false,
        'error' => 'No active coupon plan found'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 簡易割引率計算
 * まずは initial_discount_rate をそのまま使う
 * 後で decay_interval_minutes を使った本計算に差し替える
 */
$discountRate = (float)($plan['initial_discount_rate'] ?? 0);

/**
 * coupon のIDと code を生成
 */
$id = bin2hex(random_bytes(16));
$couponCode = substr(bin2hex(random_bytes(8)), 0, 8);
$now = date('c');

/**
 * coupons に保存
 */
$insert = $db->prepare("
  INSERT INTO coupons (
    id,
    coupon_code,
    coupon_plan_id,
    user_id,
    discount_value,
    status,
    issued_at,
    created_at,
    updated_at
  ) VALUES (
    :id,
    :coupon_code,
    :coupon_plan_id,
    :user_id,
    :discount_value,
    :status,
    :issued_at,
    :created_at,
    :updated_at
  )
");

$insert->execute([
    ':id' => $id,
    ':coupon_code' => $couponCode,
    ':coupon_plan_id' => $plan['id'],
    ':user_id' => null,
    ':discount_value' => (int)$discountRate,
    ':status' => 'issued',
    ':issued_at' => $now,
    ':created_at' => $now,
    ':updated_at' => $now,
]);

echo json_encode([
    'ok' => true,
    'couponId' => $couponCode,
    'coupon' => [
        'id' => $id,
        'coupon_code' => $couponCode,
        'coupon_plan_id' => $plan['id'],
        'title' => $plan['title'],
        'description' => $plan['description'],
        'discount_rate' => $discountRate,
        'issued_at' => $now,
    ]
], JSON_UNESCAPED_UNICODE);
