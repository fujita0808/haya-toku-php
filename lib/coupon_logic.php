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

    $isActiveRaw = $src['is_active'] ?? false;
    $isActive = filter_var($isActiveRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($isActive === null) {
        $isActive = in_array((string)$isActiveRaw, ['1', 'true', 'on', 'yes'], true);
    }

    return [
        'id' => $planId,
        'title' => trim((string)($src['title'] ?? '今日の早得')),
        'description' => trim((string)($src['description'] ?? '時間経過で割引率が変わるクーポンです。')),
        'product_name' => trim((string)($src['product_name'] ?? '対象商品')),
        'unit_price' => (int)($src['unit_price'] ?? 1000),
        'cost_rate' => (float)($src['cost_rate'] ?? 0.35),

        // 新仕様
        'initial_discount_rate' => (float)($src['initial_discount_rate'] ?? 0.30),
        'min_discount_rate' => (float)($src['min_discount_rate'] ?? 0.10),
        'discount_mode' => trim((string)($src['discount_mode'] ?? 'step')),
        'decay_type' => trim((string)($src['decay_type'] ?? 'step')),
        'decay_interval_minutes' => (int)($src['decay_interval_minutes'] ?? 180),
        'decay_step_rate' => (float)($src['decay_step_rate'] ?? 0.0),
        'is_active' => $isActive,

        'target_revenue' => (int)($src['target_revenue'] ?? 100000),
        'rules' => normalize_rules((string)($src['rules_text'] ?? '店頭でこの画面を提示\n1会計1回まで\n他クーポン併用不可')),
        'notes' => trim((string)($src['notes'] ?? '')),
        'updated_at' => now_iso(),
        'created_at' => trim((string)($src['created_at'] ?? now_iso())),
    ];
}

function coupon_is_currently_active(array $plan, ?DateTimeImmutable $now = null): bool
{
    unset($now); // 現仕様では時間判定しない
    return (bool)($plan['is_active'] ?? false);
}

function calculate_discount_rate(array $plan, ?DateTimeImmutable $now = null): float
{
    $issuedAt =
        (string)($plan['issued_at'] ?? '') !== '' ? (string)$plan['issued_at']
        : ((string)($plan['created_at'] ?? '') !== '' ? (string)$plan['created_at'] : now_iso());

    return calculateCurrentDiscountRate($plan, $issuedAt, $now);
}

function generate_discount_timeline(array $plan): array
{
    $initial = isset($plan['initial_discount_rate']) ? (float)$plan['initial_discount_rate'] : 0.0;
    $min = isset($plan['min_discount_rate']) ? (float)$plan['min_discount_rate'] : 0.0;
    $intervalMinutes = isset($plan['decay_interval_minutes']) ? (int)$plan['decay_interval_minutes'] : 0;
    $stepRate = isset($plan['decay_step_rate']) ? (float)$plan['decay_step_rate'] : 0.0;

    $baseAt =
        (string)($plan['created_at'] ?? '') !== '' ? (string)$plan['created_at']
        : now_iso();

    $base = new DateTimeImmutable($baseAt, new DateTimeZone('Asia/Tokyo'));

    $rows = [];
    $index = 1;
    $currentRate = $initial;
    $currentAt = $base;

    // step設定が不完全な場合は1行だけ返す
    if ($intervalMinutes <= 0 || $stepRate <= 0 || $initial <= $min) {
        return [[
            'step' => 1,
            'at' => $currentAt->format(DateTimeInterface::ATOM),
            'discount_rate' => round(max($min, $initial), 4),
        ]];
    }

    while (true) {
        $rows[] = [
            'step' => $index,
            'at' => $currentAt->format(DateTimeInterface::ATOM),
            'discount_rate' => round($currentRate, 4),
        ];

        if ($currentRate <= $min) {
            break;
        }

        $currentAt = $currentAt->modify('+' . $intervalMinutes . ' minutes');
        $currentRate = max($min, $currentRate - $stepRate);
        $index++;

        if ($index > 2000) {
            break;
        }
    }

    return $rows;
}

function find_current_plan(): ?array
{
    $sql = <<<SQL
        SELECT *
        FROM coupon_plans
        WHERE is_active = TRUE
        ORDER BY updated_at DESC NULLS LAST, created_at DESC NULLS LAST, id DESC
        LIMIT 1
    SQL;

    $stmt = db()->query($sql);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    return $plan ?: null;
}

function find_plan_by_id(string $id): ?array
{
    $sql = <<<SQL
        SELECT *
        FROM coupon_plans
        WHERE id = :id
        LIMIT 1
    SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    return $plan ?: null;
}

function upsert_plan(array $newPlan): void
{
    $sql = <<<SQL
        INSERT INTO coupon_plans (
            id,
            title,
            description,
            product_name,
            unit_price,
            cost_rate,
            initial_discount_rate,
            min_discount_rate,
            discount_mode,
            decay_type,
            decay_interval_minutes,
            decay_step_rate,
            is_active,
            target_revenue,
            rules,
            notes,
            created_at,
            updated_at
        ) VALUES (
            :id,
            :title,
            :description,
            :product_name,
            :unit_price,
            :cost_rate,
            :initial_discount_rate,
            :min_discount_rate,
            :discount_mode,
            :decay_type,
            :decay_interval_minutes,
            :decay_step_rate,
            :is_active,
            :target_revenue,
            :rules,
            :notes,
            :created_at,
            :updated_at
        )
        ON CONFLICT (id) DO UPDATE SET
            title = EXCLUDED.title,
            description = EXCLUDED.description,
            product_name = EXCLUDED.product_name,
            unit_price = EXCLUDED.unit_price,
            cost_rate = EXCLUDED.cost_rate,
            initial_discount_rate = EXCLUDED.initial_discount_rate,
            min_discount_rate = EXCLUDED.min_discount_rate,
            discount_mode = EXCLUDED.discount_mode,
            decay_type = EXCLUDED.decay_type,
            decay_interval_minutes = EXCLUDED.decay_interval_minutes,
            decay_step_rate = EXCLUDED.decay_step_rate,
            is_active = EXCLUDED.is_active,
            target_revenue = EXCLUDED.target_revenue,
            rules = EXCLUDED.rules,
            notes = EXCLUDED.notes,
            updated_at = EXCLUDED.updated_at
    SQL;

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => (string)$newPlan['id'],
        ':title' => (string)($newPlan['title'] ?? ''),
        ':description' => (string)($newPlan['description'] ?? ''),
        ':product_name' => (string)($newPlan['product_name'] ?? ''),
        ':unit_price' => (int)($newPlan['unit_price'] ?? 0),
        ':cost_rate' => (float)($newPlan['cost_rate'] ?? 0),
        ':initial_discount_rate' => (float)($newPlan['initial_discount_rate'] ?? 0),
        ':min_discount_rate' => (float)($newPlan['min_discount_rate'] ?? 0),
        ':discount_mode' => (string)($newPlan['discount_mode'] ?? 'step'),
        ':decay_type' => (string)($newPlan['decay_type'] ?? 'step'),
        ':decay_interval_minutes' => (int)($newPlan['decay_interval_minutes'] ?? 0),
        ':decay_step_rate' => (float)($newPlan['decay_step_rate'] ?? 0),
        ':is_active' => (bool)($newPlan['is_active'] ?? false),
        ':target_revenue' => (int)($newPlan['target_revenue'] ?? 0),
        ':rules' => json_encode($newPlan['rules'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':notes' => (string)($newPlan['notes'] ?? ''),
        ':created_at' => (string)($newPlan['created_at'] ?? now_iso()),
        ':updated_at' => (string)($newPlan['updated_at'] ?? now_iso()),
    ]);
}
