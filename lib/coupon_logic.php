<?php

declare(strict_types=1);

/**
 * POST → plan正規化
 */
function normalize_plan_from_post(array $input): array
{
    return [
        'title' => trim($input['title'] ?? ''),
        'description' => trim($input['description'] ?? ''),
        'product_name' => trim($input['product_name'] ?? ''),

        'unit_price' => (float)($input['unit_price'] ?? 0),
        'cost_rate' => (float)($input['cost_rate'] ?? 0),

        'initial_discount_rate' => (float)($input['initial_discount_rate'] ?? 0.3),
        'min_discount_rate' => (float)($input['min_discount_rate'] ?? 0.05),

        'expected_viewers' => (int)($input['expected_viewers'] ?? 1000),
        'target_metric' => $input['target_metric'] ?? 'gross_profit',
        'curve_step' => (float)($input['curve_step'] ?? 0.01),

        'start_at' => $input['start_at'] ?? null,
        'end_at' => $input['end_at'] ?? null,

        'is_active' => isset($input['is_active']) ? 1 : 0,
    ];
}

/**
 * DB row → plan
 */
function decode_plan_row(array $row): array
{
    return [
        'id' => $row['id'] ?? null,
        'title' => $row['title'] ?? '',
        'description' => $row['description'] ?? '',
        'product_name' => $row['product_name'] ?? '',

        'unit_price' => (float)($row['unit_price'] ?? 0),
        'cost_rate' => (float)($row['cost_rate'] ?? 0),

        'initial_discount_rate' => (float)($row['initial_discount_rate'] ?? 0.3),
        'min_discount_rate' => (float)($row['min_discount_rate'] ?? 0.05),

        'expected_viewers' => (int)($row['expected_viewers'] ?? 1000),
        'target_metric' => $row['target_metric'] ?? 'gross_profit',
        'curve_step' => (float)($row['curve_step'] ?? 0.01),

        'start_at' => $row['start_at'] ?? null,
        'end_at' => $row['end_at'] ?? null,

        'is_active' => (bool)($row['is_active'] ?? false),
    ];
}