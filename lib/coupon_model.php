<?php

declare(strict_types=1);

/**
 * 割引率 → 想定利用率モデル
 *
 * 前提:
 * y = a * e^(b * x)
 *
 * x: 割引率（0.0 ～ 1.0）
 * y: 想定利用率（0.0 ～ 1.0）
 */
const HAYA_TOKU_USAGE_MODEL_A = 0.0100;
const HAYA_TOKU_USAGE_MODEL_B = 4.14;

/**
 * 割引率から想定利用率を返す
 */
function estimate_coupon_usage_rate(float $discountRate): float
{
    $x = max(0.0, min(1.0, $discountRate));
    $y = HAYA_TOKU_USAGE_MODEL_A * exp(HAYA_TOKU_USAGE_MODEL_B * $x);

    // 利用率は 0～1 に丸める
    return max(0.0, min(1.0, $y));
}

/**
 * 割引率から1件あたり売上額を返す
 *
 * unitPrice: 税抜単価など
 * discountRate: 0.30 = 30%
 */
function calc_discounted_unit_price(float $unitPrice, float $discountRate): float
{
    $discountRate = max(0.0, min(1.0, $discountRate));
    return max(0.0, $unitPrice * (1.0 - $discountRate));
}

/**
 * 割引率から1件あたり粗利益額を返す
 *
 * costRate: 0.35 = 原価率35%
 */
function calc_unit_gross_profit(float $unitPrice, float $costRate, float $discountRate): float
{
    $discountRate = max(0.0, min(1.0, $discountRate));
    $costRate = max(0.0, min(1.0, $costRate));

    $sales = $unitPrice * (1.0 - $discountRate);
    $cost = $unitPrice * $costRate;

    return $sales - $cost;
}

/**
 * 割引率ごとの期待売上・期待粗利益を返す
 *
 * viewers:
 *   その日その割引率で見せた対象人数
 */
function simulate_discount_point(
    float $unitPrice,
    float $costRate,
    float $discountRate,
    int $viewers
): array {
    $usageRate = estimate_coupon_usage_rate($discountRate);
    $expectedUsers = $viewers * $usageRate;

    $unitSales = calc_discounted_unit_price($unitPrice, $discountRate);
    $unitGrossProfit = calc_unit_gross_profit($unitPrice, $costRate, $discountRate);

    $expectedSales = $expectedUsers * $unitSales;
    $expectedGrossProfit = $expectedUsers * $unitGrossProfit;

    return [
        'discount_rate' => $discountRate,
        'usage_rate' => $usageRate,
        'expected_users' => $expectedUsers,
        'unit_sales' => $unitSales,
        'unit_gross_profit' => $unitGrossProfit,
        'expected_sales' => $expectedSales,
        'expected_gross_profit' => $expectedGrossProfit,
    ];
}

/**
 * 指定範囲で理想カーブを走査して、最大点を返す
 *
 * mode:
 *   'sales'        = 売上最大
 *   'gross_profit' = 粗利益最大
 */
function find_optimal_discount_rate(
    float $unitPrice,
    float $costRate,
    int $viewers,
    float $minDiscountRate,
    float $maxDiscountRate,
    float $step = 0.001,
    string $mode = 'gross_profit'
): array {
    $best = null;

    for ($d = $minDiscountRate; $d <= $maxDiscountRate + 1e-9; $d += $step) {
        $row = simulate_discount_point($unitPrice, $costRate, $d, $viewers);

        $score = match ($mode) {
            'sales' => $row['expected_sales'],
            default => $row['expected_gross_profit'],
        };

        if ($best === null || $score > $best['score']) {
            $best = $row + [
                'score' => $score,
                'mode' => $mode,
            ];
        }
    }

    return $best;
}

/**
 * グラフ描画や管理画面表示用のカーブ配列を返す
 */
function build_discount_curve(
    float $unitPrice,
    float $costRate,
    int $viewers,
    float $minDiscountRate,
    float $maxDiscountRate,
    float $step = 0.01
): array {
    $rows = [];

    for ($d = $minDiscountRate; $d <= $maxDiscountRate + 1e-9; $d += $step) {
        $rows[] = simulate_discount_point($unitPrice, $costRate, round($d, 4), $viewers);
    }

    return $rows;
}