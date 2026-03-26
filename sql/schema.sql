BEGIN;

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
);

CREATE TABLE IF NOT EXISTS coupons (
    id BIGSERIAL PRIMARY KEY,
    coupon_code VARCHAR(32) NOT NULL UNIQUE,
    coupon_plan_id VARCHAR(64) NOT NULL REFERENCES coupon_plans(id) ON DELETE CASCADE,

    issued_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,

    used_discount_rate NUMERIC(5,4) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usage_logs (
    id BIGSERIAL PRIMARY KEY,
    coupon_id BIGINT NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    coupon_plan_id VARCHAR(64) NOT NULL REFERENCES coupon_plans(id) ON DELETE CASCADE,

    used_at TIMESTAMP NOT NULL,
    used_discount_rate NUMERIC(5,4) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_coupon_plans_is_active
    ON coupon_plans (is_active);

CREATE INDEX IF NOT EXISTS idx_coupon_plans_updated_at
    ON coupon_plans (updated_at DESC);

CREATE INDEX IF NOT EXISTS idx_coupons_coupon_plan_id
    ON coupons (coupon_plan_id);

CREATE INDEX IF NOT EXISTS idx_coupons_used_at
    ON coupons (used_at);

CREATE INDEX IF NOT EXISTS idx_usage_logs_coupon_id
    ON usage_logs (coupon_id);

CREATE INDEX IF NOT EXISTS idx_usage_logs_coupon_plan_id
    ON usage_logs (coupon_plan_id);

CREATE INDEX IF NOT EXISTS idx_usage_logs_used_at
    ON usage_logs (used_at DESC);

COMMIT;