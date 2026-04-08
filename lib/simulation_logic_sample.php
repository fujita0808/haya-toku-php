<?php
declare(strict_types=1);

/**
 * simulation_logic_sample.php
 *
 * スプレッドシート「クーポン企画シュミレート」タブを
 * PHPへ移植するための核となるロジック雛形。
 *
 * 役割:
 * - 入力値バリデーション
 * - シミュレーション行データ生成
 * - summary + rows を返す
 *
 * 想定:
 * - admin/user_dashboard.php から require される
 * - coupon_plans の入力値を受け取り、結果表用配列を返す
 */

function simulateCouponPlan(array $input): array
{
    validateSimulationInput($input);

    $unitPrice = (int)$input['unit_price'];
    $costRate = (float)$input['cost_rate'];
    $initialDiscount = (float)$input['initial_discount_rate'];
    $minDiscount = (float)$input['min_discount_rate'];
    $targetRevenue = (int)$input['target_revenue'];
    $intervalMinutes = (int)$input['decay_interval_minutes'];

    $startAt = new DateTimeImmutable($input['start_at']);
    $endAt = new DateTimeImmutable($input['end_at']);

    $durationSeconds = max(0, $endAt->getTimestamp() - $startAt->getTimestamp());
    $durationDays = max(1, (int)ceil($durationSeconds / 86400));
    $stepCount = max(1, (int)ceil($durationSeconds / max(60, $intervalMinutes * 60)));
    $decayPerStep = $stepCount > 1
        ? (($initialDiscount - $minDiscount) / ($stepCount - 1))
        : 0.0;

    $discountRates = [];
    for ($i = 0; $i < $stepCount; $i++) {
        $discountRates[] = max($minDiscount, $initialDiscount - ($decayPerStep * $i));
    }

    // ここはスプレッドシート/GAS準拠ロジックへ差し替え前提
    $avgDiscountRate = array_sum($discountRates) / max(1, count($discountRates));
    $avgSellingPrice = max(1, (int)round($unitPrice * (1 - $avgDiscountRate)));
    $estimatedCvTotal = max(1, (int)round($targetRevenue / $avgSellingPrice));

    $rows = [];
    $totalCv = 0;
    $totalRevenue = 0;
    $totalDiscount = 0;
    $totalGross = 0;

    foreach ($discountRates as $index => $rate) {
        $step = $index + 1;
        $date = $startAt->modify('+' . ($index * $intervalMinutes) . ' minutes');

        $weight = exp(5 * $rate);
        $cvCount = max(1, (int)round(($estimatedCvTotal / $stepCount) * ($weight / exp(5 * $avgDiscountRate))));
        $sellingPrice = (int)round($unitPrice * (1 - $rate));
        $revenue = $sellingPrice * $cvCount;
        $discountValue = (int)round(($unitPrice * $rate) * $cvCount);
        $gross = (int)round(($sellingPrice - ($unitPrice * $costRate)) * $cvCount);

        $totalCv += $cvCount;
        $totalRevenue += $revenue;
        $totalDiscount += $discountValue;
        $totalGross += $gross;

        $rows[] = [
            'step' => $step,
            'date' => $date->format('Y/m/d'),
            'discount_rate' => round($rate, 4),
            'cv_count' => $cvCount,
            'revenue' => $revenue,
            'discount_value' => $discountValue,
            'gross' => $gross,
        ];
    }

    $cumulativeCv = 0;
    $cumulativeRevenue = 0;
    $cumulativeDiscount = 0;
    $cumulativeGross = 0;

    foreach ($rows as &$row) {
        $cumulativeCv += $row['cv_count'];
        $cumulativeRevenue += $row['revenue'];
        $cumulativeDiscount += $row['discount_value'];
        $cumulativeGross += $row['gross'];

        $row['cumulative_cv'] = $cumulativeCv;
        $row['cv_ratio'] = $totalCv > 0 ? round(($row['cv_count'] / $totalCv) * 100, 2) : 0;
        $row['cumulative_revenue'] = $cumulativeRevenue;
        $row['revenue_ratio'] = $totalRevenue > 0 ? round(($row['revenue'] / $totalRevenue) * 100, 2) : 0;
        $row['cumulative_discount'] = $cumulativeDiscount;
        $row['discount_ratio'] = $totalDiscount > 0 ? round(($row['discount_value'] / $totalDiscount) * 100, 2) : 0;
        $row['cumulative_gross'] = $cumulativeGross;
        $row['gross_ratio'] = $totalGross > 0 ? round(($row['gross'] / $totalGross) * 100, 2) : 0;
        $row['unit_profit'] = $row['cv_count'] > 0 ? (int)round($row['gross'] / $row['cv_count']) : 0;
        $row['unit_discount_rate'] = round($row['discount_rate'] * 100, 2);
        $row['unit_discount'] = $row['cv_count'] > 0 ? (int)round($row['discount_value'] / $row['cv_count']) : 0;
        $row['unit_profit_rate'] = $unitPrice > 0 ? round(($row['unit_profit'] / $unitPrice) * 100, 2) : 0;
    }
    unset($row);

    return [
        'summary' => [
            'duration_days' => $durationDays,
            'step_count' => $stepCount,
            'decay_per_step' => round($decayPerStep, 6),
            'total_cv' => $totalCv,
            'total_revenue' => $totalRevenue,
            'total_discount' => $totalDiscount,
            'total_gross' => $totalGross,
        ],
        'rows' => $rows,
    ];
}

function validateSimulationInput(array $input): void
{
    $required = [
        'title',
        'product_name',
        'unit_price',
        'cost_rate',
        'initial_discount_rate',
        'min_discount_rate',
        'decay_interval_minutes',
        'start_at',
        'end_at',
        'target_revenue',
    ];

    foreach ($required as $key) {
        if (!isset($input[$key]) || trim((string)$input[$key]) === '') {
            throw new InvalidArgumentException("{$key} は必須です。");
        }
    }

    if ((float)$input['min_discount_rate'] > (float)$input['initial_discount_rate']) {
        throw new InvalidArgumentException('最低割引率は初期割引率以下にしてください。');
    }

    $start = new DateTimeImmutable($input['start_at']);
    $end = new DateTimeImmutable($input['end_at']);

    if ($start >= $end) {
        throw new InvalidArgumentException('終了日時は開始日時より後にしてください。');
    }
}
