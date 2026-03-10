<?php

declare(strict_types=1);

function generate_plan_id(): string
{
    return 'plan_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
}

function normalize_rules(string $rulesText): array
{
    $lines = preg_split('/\R/u', $rulesText) ?: [];
    $rules = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $rules[] = $line;
        }
    }
    return $rules;
}

function normalize_plan_from_post(?array $source = null): array
{
    $src = $source ?? $_POST;
    $planId = trim((string)($src['id'] ?? ''));
    if ($planId === '') {
        $planId = generate_plan_id();
    }

    return [
        'id' => $planId,
        'title' => trim((string)($src['title'] ?? '今日の早得')),
        'description' => trim((string)($src['description'] ?? '時間経過で割引率が変わるクーポンです。')),
        'status' => trim((string)($src['status'] ?? 'draft')),
        'product_name' => trim((string)($src['product_name'] ?? '対象商品')),
        'unit_price' => (int)($src['unit_price'] ?? 1000),
        'cost_rate' => (float)($src['cost_rate'] ?? 0.35),
        'initial_discount_rate' => (float)($src['initial_discount_rate'] ?? 0.30),
        'min_discount_rate' => (float)($src['min_discount_rate'] ?? 0.10),
        'decay_interval_minutes' => (int)($src['decay_interval_minutes'] ?? 180),
        'start_at' => trim((string)($src['start_at'] ?? '')),
        'end_at' => trim((string)($src['end_at'] ?? '')),
        'target_revenue' => (int)($src['target_revenue'] ?? 100000),
        'rules' => normalize_rules((string)($src['rules_text'] ?? '店頭でこの画面を提示\n1会計1回まで\n他クーポン併用不可')),
        'notes' => trim((string)($src['notes'] ?? '')),
        'updated_at' => now_iso(),
        'created_at' => trim((string)($src['created_at'] ?? now_iso())),
    ];
}

function coupon_is_currently_active(array $plan, ?DateTimeImmutable $now = null): bool
{
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
    if (($plan['status'] ?? 'draft') !== 'active') {
        return false;
    }
    $startAt = new DateTimeImmutable((string)$plan['start_at'], new DateTimeZone('Asia/Tokyo'));
    $endAt = new DateTimeImmutable((string)$plan['end_at'], new DateTimeZone('Asia/Tokyo'));
    return $now >= $startAt && $now <= $endAt;
}

function calculate_discount_rate(array $plan, ?DateTimeImmutable $now = null): float
{
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
    $startAt = new DateTimeImmutable((string)$plan['start_at'], new DateTimeZone('Asia/Tokyo'));

    $initial = (float)$plan['initial_discount_rate'];
    $minimum = (float)$plan['min_discount_rate'];
    $intervalMinutes = max((int)$plan['decay_interval_minutes'], 1);

    $elapsedSeconds = max($now->getTimestamp() - $startAt->getTimestamp(), 0);
    $steps = (int)floor($elapsedSeconds / ($intervalMinutes * 60));

    $endAt = new DateTimeImmutable((string)$plan['end_at'], new DateTimeZone('Asia/Tokyo'));
    $totalSeconds = max($endAt->getTimestamp() - $startAt->getTimestamp(), 60);
    $stepCount = max((int)ceil($totalSeconds / ($intervalMinutes * 60)), 1);
    $decayPerStep = ($initial - $minimum) / max($stepCount - 1, 1);

    $rate = $initial - ($steps * $decayPerStep);
    return max($rate, $minimum);
}

function generate_discount_timeline(array $plan): array
{
    $startAt = new DateTimeImmutable((string)$plan['start_at'], new DateTimeZone('Asia/Tokyo'));
    $endAt = new DateTimeImmutable((string)$plan['end_at'], new DateTimeZone('Asia/Tokyo'));
    $intervalMinutes = max((int)$plan['decay_interval_minutes'], 1);
    $current = $startAt;
    $rows = [];
    $index = 1;
    while ($current <= $endAt) {
        $rows[] = [
            'step' => $index,
            'at' => $current->format(DateTimeInterface::ATOM),
            'discount_rate' => round(calculate_discount_rate($plan, $current), 4),
        ];
        $current = $current->modify('+' . $intervalMinutes . ' minutes');
        $index++;
        if ($index > 2000) {
            break;
        }
    }
    return $rows;
}

function find_current_plan(): ?array
{
    $data = load_coupon_plans();
    foreach ($data['plans'] as $plan) {
        if (coupon_is_currently_active($plan)) {
            return $plan;
        }
    }
    return null;
}

function find_plan_by_id(string $id): ?array
{
    $data = load_coupon_plans();
    foreach ($data['plans'] as $plan) {
        if (($plan['id'] ?? '') === $id) {
            return $plan;
        }
    }
    return null;
}

function upsert_plan(array $newPlan): void
{
    $data = load_coupon_plans();
    $plans = $data['plans'] ?? [];
    $updated = false;
    foreach ($plans as $index => $plan) {
        if (($plan['id'] ?? '') === $newPlan['id']) {
            $plans[$index] = $newPlan;
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        array_unshift($plans, $newPlan);
    }
    save_coupon_plans($plans);
}
