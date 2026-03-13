<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/api_headers.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();

/**
 * GET / POST 両対応
 */
$couponCode = $_POST['couponId'] ?? ($_GET['couponId'] ?? null);

if (!$couponCode) {
    echo json_encode([
        'ok' => false,
        'error' => 'couponId is required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * coupons から対象を取得
 */
$stmt = $db->prepare("
  SELECT *
  FROM coupons
  WHERE coupon_code = :coupon_code
  LIMIT 1
");

$stmt->execute([
    ':coupon_code' => $couponCode
]);

$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    echo json_encode([
        'ok' => false,
        'error' => 'Coupon not found'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($coupon['status'] ?? '') === 'used') {
    echo json_encode([
        'ok' => false,
        'error' => 'Coupon already used'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$logId = bin2hex(random_bytes(16));
$now = date('c');

/**
 * usage_logs に保存
 * 今回は coupon_id ベース
 */
$insert = $db->prepare("
  INSERT INTO usage_logs (
    id,
    coupon_id,
    user_id,
    display_name,
    discount_rate,
    discounted_price,
    used_at
  ) VALUES (
    :id,
    :coupon_id,
    :user_id,
    :display_name,
    :discount_rate,
    :discounted_price,
    :used_at
  )
");

$insert->execute([
    ':id' => $logId,
    ':coupon_id' => $coupon['id'],
    ':user_id' => null,
    ':display_name' => null,
    ':discount_rate' => $coupon['discount_value'],
    ':discounted_price' => null,
    ':used_at' => $now,
]);

/**
 * coupons を used に更新
 */
$update = $db->prepare("
  UPDATE coupons
  SET status = :status,
      used_at = :used_at,
      updated_at = :updated_at
  WHERE id = :id
");

$update->execute([
    ':status' => 'used',
    ':used_at' => $now,
    ':updated_at' => $now,
    ':id' => $coupon['id'],
]);

echo json_encode([
    'ok' => true,
    'message' => 'Coupon used successfully',
    'couponId' => $couponCode,
    'coupon_id' => $coupon['id'],
    'used_at' => $now
], JSON_UNESCAPED_UNICODE);
