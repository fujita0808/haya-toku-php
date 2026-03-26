<?php

declare(strict_types=1);

/**
 * 割引率を0〜1に丸める
 */
function normalize_discount_rate(float $rate): float
{
    return max(0.0, min(1.0, $rate));
}

/**
 * 現在時刻（Tokyo）
 */
function now_tokyo(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * 公開中か
 */
function is_plan_public_now(array $plan, ?string $now = null): bool
{
    $now = $now ?? now_tokyo();

    if (empty($plan['start_at']) || empty($plan['end_at'])) {
        return false;
    }

    return ($now >= $plan['start_at'] && $now <= $plan['end_at']);
}

/**
 * 発行可能か
 */
function is_plan_issuable_now(array $plan, ?string $now = null): bool
{
    if (!$plan['is_active']) {
        return false;
    }

    return is_plan_public_now($plan, $now);
}

/**
 * 公開日数
 */
function get_public_period_days(array $plan): int
{
    $start = strtotime($plan['start_at']);
    $end   = strtotime($plan['end_at']);

    if (!$start || !$end || $end < $start) {
        return 1;
    }

    return max(1, (int)floor(($end - $start) / 86400) + 1);
}

/**
 * 経過日数
 */
function get_elapsed_days(array $plan, ?string $now = null): int
{
    $now = $now ?? now_tokyo();

    $start = strtotime($plan['start_at']);
    $current = strtotime($now);

    if (!$start || !$current || $current < $start) {
        return 0;
    }

    return (int)floor(($current - $start) / 86400);
}

/**
 * model用入力
 */
function get_model_inputs(array $plan): array
{
    return [
        'unit_price'        => (float)($plan['unit_price'] ?? 1000),
        'cost_rate'         => (float)($plan['cost_rate'] ?? 0.3),
        'viewers'           => (int)($plan['expected_viewers'] ?? 1000),
        'min_rate'          => (float)($plan['min_discount_rate'] ?? 0.05),
        'max_rate'          => (float)($plan['initial_discount_rate'] ?? 0.5),
        'step'              => (float)($plan['curve_step'] ?? 0.01),
        'mode'              => (string)($plan['target_metric'] ?? 'gross_profit'),
    ];
}

/**
 * 割引率カーブ生成
 */
function build_plan_discount_timeline(array $plan): array
{
    $inputs = get_model_inputs($plan);

    $curve = build_discount_curve(
        $inputs['unit_price'],
        $inputs['cost_rate'],
        $inputs['viewers'],
        $inputs['min_rate'],
        $inputs['max_rate'],
        $inputs['step']
    );

    $days = get_public_period_days($plan);
    $result = [];

    if (empty($curve)) {
        return [];
    }

    for ($i = 0; $i < $days; $i++) {
        $index = min($i, count($curve) - 1);
        $rate = normalize_discount_rate($curve[$index]['discount_rate']);

        $result[] = [
            'day' => $i,
            'discount_rate' => $rate,
            'discount_percent' => round($rate * 100, 2),
        ];
    }

    return $result;
}

/**
 * 現在割引率
 */
function get_current_plan_discount_rate(array $plan, ?string $now = null): ?float
{
    if (!is_plan_public_now($plan, $now)) {
        return null;
    }

    $timeline = build_plan_discount_timeline($plan);
    $elapsed = get_elapsed_days($plan, $now);

    if (!isset($timeline[$elapsed])) {
        return end($timeline)['discount_rate'] ?? null;
    }

    return $timeline[$elapsed]['discount_rate'];
}

/**
 * API用payload
 */
function get_current_plan_discount_payload(array $plan, ?string $now = null): array
{
    $rate = get_current_plan_discount_rate($plan, $now);

    return [
        'discount_rate' => $rate,
        'discount_percent' => $rate !== null ? round($rate * 100, 2) : null,
        'is_issuable' => is_plan_issuable_now($plan, $now),
        'elapsed_days' => get_elapsed_days($plan, $now),
        'message' => $rate === null
            ? '公開期間外です'
            : '現在の割引率です（未使用クーポンは日々変動します）',
    ];
}