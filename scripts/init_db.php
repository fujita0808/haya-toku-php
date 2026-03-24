<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

try {
    $pdo = db();

    $queries = [];

    $queries[] = <<<SQL
CREATE TABLE IF NOT EXISTS coupon_plans (
    id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    product_name VARCHAR(255),
    start_at TIMESTAMP NOT NULL,
    end_at TIMESTAMP NOT NULL,
    initial_discount_rate NUMERIC(5,4) NOT NULL,
    min_discount_rate NUMERIC(5,4) NOT NULL,

    -- 0324仕様では linear / daily_linear を想定
    discount_mode VARCHAR(50),
    decay_type VARCHAR(50),

    -- 旧仕様互換カラム（未使用）
    decay_interval_minutes INTEGER NULL,
    decay_step_rate NUMERIC(8,4) NULL,

    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    rules TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL;

    $queries[] = <<<SQL
CREATE TABLE IF NOT EXISTS coupons (
    id BIGSERIAL PRIMARY KEY,
    coupon_code VARCHAR(64) NOT NULL UNIQUE,
    coupon_plan_id VARCHAR(64) NOT NULL REFERENCES coupon_plans(id) ON DELETE CASCADE,

    -- 発行時点で確定した割引率（主役）
    issued_at TIMESTAMP NOT NULL,
    issued_discount_rate NUMERIC(5,4) NOT NULL,

    -- 使用時の記録
    used_at TIMESTAMP NULL,
    used_discount_rate NUMERIC(5,4) NULL,

    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL;

    $queries[] = <<<SQL
CREATE TABLE IF NOT EXISTS usage_logs (
    id BIGSERIAL PRIMARY KEY,
    coupon_id BIGINT NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    coupon_code VARCHAR(64),
    event_type VARCHAR(50) NOT NULL,
    event_at TIMESTAMP NOT NULL,
    discount_rate NUMERIC(5,4) NULL,
    meta_json TEXT NULL,
    created_at TIMESTAMP NOT NULL
)
SQL;

    $queries[] = <<<SQL
CREATE INDEX IF NOT EXISTS idx_coupon_plans_is_active
ON coupon_plans (is_active)
SQL;

    $queries[] = <<<SQL
CREATE INDEX IF NOT EXISTS idx_coupons_coupon_plan_id
ON coupons (coupon_plan_id)
SQL;

    $queries[] = <<<SQL
CREATE INDEX IF NOT EXISTS idx_coupons_coupon_code
ON coupons (coupon_code)
SQL;

    $queries[] = <<<SQL
CREATE INDEX IF NOT EXISTS idx_usage_logs_coupon_id
ON usage_logs (coupon_id)
SQL;

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }

    echo "DB initialized\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB init failed: ' . $e->getMessage() . "\n";
}