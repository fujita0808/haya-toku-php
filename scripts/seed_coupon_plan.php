<?php

require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();

$id = 'plan_' . bin2hex(random_bytes(4));
$now = date('c');
$startAt = date('c', strtotime('-1 hour'));
$endAt = date('c', strtotime('+1 day'));

$stmt = $db->prepare("
  INSERT INTO coupon_plans (
    id,
    title,
    description,
    status,
    product_name,
    unit_price,
    cost_rate,
    initial_discount_rate,
    min_discount_rate,
    decay_interval_minutes,
    start_at,
    end_at,
    target_revenue,
    rules_json,
    notes,
    created_at,
    updated_at
  ) VALUES (
    :id,
    :title,
    :description,
    :status,
    :product_name,
    :unit_price,
    :cost_rate,
    :initial_discount_rate,
    :min_discount_rate,
    :decay_interval_minutes,
    :start_at,
    :end_at,
    :target_revenue,
    :rules_json,
    :notes,
    :created_at,
    :updated_at
  )
");

$stmt->execute([
  ':id' => $id,
  ':title' => '朝得クーポン',
  ':description' => '本日のテスト用クーポンです',
  ':status' => 'active',
  ':product_name' => 'テスト商品',
  ':unit_price' => 1000,
  ':cost_rate' => 0.5,
  ':initial_discount_rate' => 20,
  ':min_discount_rate' => 10,
  ':decay_interval_minutes' => 30,
  ':start_at' => $startAt,
  ':end_at' => $endAt,
  ':target_revenue' => 10000,
  ':rules_json' => json_encode([
    'memo' => 'temporary seed data'
  ], JSON_UNESCAPED_UNICODE),
  ':notes' => 'seed data',
  ':created_at' => $now,
  ':updated_at' => $now,
]);

echo json_encode([
  'ok' => true,
  'message' => 'coupon_plans seeded',
  'id' => $id,
  'start_at' => $startAt,
  'end_at' => $endAt
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
