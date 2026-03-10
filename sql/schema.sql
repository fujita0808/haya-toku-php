-- HAYA-TOKU PoC: MySQL schema draft

CREATE TABLE admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login_id VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE coupon_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  product_name VARCHAR(255) NULL,
  unit_price INT NOT NULL DEFAULT 0,
  cost_rate DECIMAL(6,4) NOT NULL DEFAULT 0.3500,
  initial_discount_rate DECIMAL(6,4) NOT NULL,
  min_discount_rate DECIMAL(6,4) NOT NULL,
  decay_interval_minutes INT NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  target_revenue INT NOT NULL DEFAULT 0,
  status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
  rules_json JSON NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE usage_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_plan_id BIGINT UNSIGNED NOT NULL,
  user_id VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) NULL,
  discount_rate DECIMAL(6,4) NOT NULL,
  discounted_price INT NOT NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_coupon_plan_id (coupon_plan_id),
  INDEX idx_user_id (user_id),
  CONSTRAINT fk_usage_coupon_plans FOREIGN KEY (coupon_plan_id) REFERENCES coupon_plans(id)
);
