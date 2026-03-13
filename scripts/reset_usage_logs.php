<?php

require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$db = db();

$sql = <<<SQL
DROP TABLE IF EXISTS usage_logs;

CREATE TABLE IF NOT EXISTS usage_logs (
  id TEXT PRIMARY KEY,
  coupon_id TEXT,
  user_id TEXT,
  display_name TEXT,
  discount_rate NUMERIC,
  discounted_price INTEGER,
  used_at TIMESTAMPTZ
);
SQL;

$db->exec($sql);

echo json_encode([
  'ok' => true,
  'message' => 'usage_logs reset completed'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
