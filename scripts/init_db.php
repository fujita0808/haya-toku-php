<?php

require_once __DIR__ . '/../lib/bootstrap.php';

$sql = <<<SQL

CREATE TABLE IF NOT EXISTS coupon_plans (
  id TEXT PRIMARY KEY,
  title TEXT,
  description TEXT,
  status TEXT,
  product_name TEXT,
  unit_price INTEGER,
  cost_rate NUMERIC,
  initial_discount_rate NUMERIC,
  min_discount_rate NUMERIC,
  decay_interval_minutes INTEGER,
  start_at TIMESTAMPTZ,
  end_at TIMESTAMPTZ,
  target_revenue INTEGER,
  rules_json JSONB,
  notes TEXT,
  created_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS usage_logs (
  id TEXT PRIMARY KEY,
  coupon_id TEXT,
  user_id TEXT,
  display_name TEXT,
  discount_rate NUMERIC,
  discounted_price INTEGER,
  used_at TIMESTAMPTZ
);
  
CREATE TABLE IF NOT EXISTS coupons (
  id TEXT PRIMARY KEY,
  coupon_code TEXT UNIQUE NOT NULL,
  coupon_plan_id TEXT NOT NULL,
  user_id TEXT,
  discount_value INTEGER,
  status TEXT,
  issued_at TIMESTAMPTZ,
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ
);
  
SQL;

db()->exec($sql);

echo "DB initialized";
