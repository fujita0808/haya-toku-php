<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/api_headers.php';
require_once __DIR__ . '/../lib/db.php';

$db = db();

/**
 * 現在有効なクーポンプランを1件取得
 * 今の運用では is_active = TRUE を有効とみなす
 */
$stmt = $db->query("
  SELECT *
  FROM coupon_plans
  WHERE is_active = TRUE
  ORDER BY id ASC
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
 * 発行時点の割引率
 * 今は発行直後なので initial_discount_rate をそのまま返す
 * 実際の利用時は coupon_get.php / coupon_use.php 側で再計算する
 */
$discountRate = (float)($plan['initial_discount_rate'] ?? 0);

/**
 * coupon のIDと code を生成
 */
$id = bin2hex(random_bytes(16));
$couponCode = substr(bin2hex(random_bytes(8)), 0, 8);
$now = date('c');

/**
 * coupons.discount_value の型が旧仕様の整数前提なら
 * 0.2 → 20 にして保存する
 */
$discountValue = (int)round($discountRate * 100);

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
    ':discount_value' => $discountValue,
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
        'title' => $plan['title'] ?? null,
        'description' => $plan['description'] ?? null,
        'discount_rate' => $discountRate,
        'discount_percent' => round($discountRate * 100, 2),
        'issued_at' => $now,
    ]
], JSON_UNESCAPED_UNICODE);
