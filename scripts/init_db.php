<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

try {
    $pdo = db();

    $pdo->beginTransaction();

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupon_plans (
    id VARCHAR(64) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    product_name VARCHAR(255) NOT NULL DEFAULT '',

    unit_price NUMERIC(10,2) NOT NULL DEFAULT 0,
    cost_rate NUMERIC(5,4) NOT NULL DEFAULT 0.3000,

    initial_discount_rate NUMERIC(5,4) NOT NULL DEFAULT 0.3000,
    min_discount_rate NUMERIC(5,4) NOT NULL DEFAULT 0.0500,

    expected_viewers INTEGER NOT NULL DEFAULT 1000,
    target_metric VARCHAR(32) NOT NULL DEFAULT 'gross_profit',
    curve_step NUMERIC(5,4) NOT NULL DEFAULT 0.0100,

    start_at TIMESTAMP NOT NULL,
    end_at TIMESTAMP NOT NULL,

    is_active BOOLEAN NOT NULL DEFAULT FALSE,

    rules JSONB NOT NULL DEFAULT '[]'::jsonb,
    notes TEXT NOT NULL DEFAULT '',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS coupons (
    id BIGSERIAL PRIMARY KEY,
    coupon_code VARCHAR(32) NOT NULL UNIQUE,
    coupon_plan_id VARCHAR(64) NOT NULL REFERENCES coupon_plans(id) ON DELETE CASCADE,

    issued_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,

    used_discount_rate NUMERIC(5,4) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS usage_logs (
    id BIGSERIAL PRIMARY KEY,
    coupon_id BIGINT NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    coupon_plan_id VARCHAR(64) NOT NULL REFERENCES coupon_plans(id) ON DELETE CASCADE,

    used_at TIMESTAMP NOT NULL,
    used_discount_rate NUMERIC(5,4) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coupon_plans_is_active ON coupon_plans (is_active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coupon_plans_updated_at ON coupon_plans (updated_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coupons_coupon_plan_id ON coupons (coupon_plan_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coupons_used_at ON coupons (used_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usage_logs_coupon_id ON usage_logs (coupon_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usage_logs_coupon_plan_id ON usage_logs (coupon_plan_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usage_logs_used_at ON usage_logs (used_at DESC)");

    /*
     * 既存環境の移行用
     * 旧カラムが残っていても、まずは新仕様に必要なカラムを揃える
     */
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS unit_price NUMERIC(10,2) NOT NULL DEFAULT 0");
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS cost_rate NUMERIC(5,4) NOT NULL DEFAULT 0.3000");
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS expected_viewers INTEGER NOT NULL DEFAULT 1000");
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS target_metric VARCHAR(32) NOT NULL DEFAULT 'gross_profit'");
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS curve_step NUMERIC(5,4) NOT NULL DEFAULT 0.0100");
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS rules JSONB NOT NULL DEFAULT '[]'::jsonb");
    $pdo->exec("ALTER TABLE coupon_plans ADD COLUMN IF NOT EXISTS notes TEXT NOT NULL DEFAULT ''");

    $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS used_discount_rate NUMERIC(5,4)");
    $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS issued_at TIMESTAMP");
    $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS used_at TIMESTAMP");
    $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    $pdo->exec("ALTER TABLE usage_logs ADD COLUMN IF NOT EXISTS coupon_id BIGINT");
    $pdo->exec("ALTER TABLE usage_logs ADD COLUMN IF NOT EXISTS coupon_plan_id VARCHAR(64)");
    $pdo->exec("ALTER TABLE usage_logs ADD COLUMN IF NOT EXISTS used_at TIMESTAMP");
    $pdo->exec("ALTER TABLE usage_logs ADD COLUMN IF NOT EXISTS used_discount_rate NUMERIC(5,4)");
    $pdo->exec("ALTER TABLE usage_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    /*
     * 旧仕様で percent整数(20,10) が入っていた場合の保険
     */
    $pdo->exec(<<<'SQL'
UPDATE coupon_plans
SET
    initial_discount_rate = initial_discount_rate / 100.0,
    min_discount_rate = min_discount_rate / 100.0
WHERE initial_discount_rate > 1
   OR min_discount_rate > 1
SQL);

    /*
     * 必須カラムのnull補正
     */
    $pdo->exec("UPDATE coupons SET issued_at = created_at WHERE issued_at IS NULL");
    $pdo->exec("UPDATE coupons SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL");

    $pdo->commit();

    header('Content-Type: text/plain; charset=utf-8');
    echo "DB initialized\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'DB init failed: ' . $e->getMessage();
}