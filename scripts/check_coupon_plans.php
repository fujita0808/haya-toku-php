<?php

require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$stmt = db()->query("
  SELECT
    id,
    title,
    status,
    start_at,
    end_at,
    initial_discount_rate,
    min_discount_rate,
    decay_interval_minutes,
    updated_at
  FROM coupon_plans
  ORDER BY updated_at DESC NULLS LAST, created_at DESC NULLS LAST
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'count' => count($rows),
  'rows' => $rows
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
