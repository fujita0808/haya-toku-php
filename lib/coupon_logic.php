<?php

declare(strict_types=1);

/**
 * 管理画面POSTからプランを正規化する
 * 0324仕様:
 * - 時間単位減衰は不採用
 * - 発行時固定
 * - 線形減衰
 * - 公開期間依存
 */
function normalize_plan_from_post(array $post): array
{
    $id = trim((string)($post['id'] ?? ''));
    if ($id === '') {
        $id = generate_plan_id();
    }

    $title = trim((string)($post['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('タイトルを入力してください。');
    }

    $description = trim((string)($post['description'] ?? ''));
    $productName = trim((string)($post['product_name'] ?? ''));
    $notes = trim((string)($post['notes'] ?? ''));

    $rulesText = trim((string)($post['rules_text'] ?? ''));
    $rules = array_values(
        array_filter(
            array_map('trim', preg_split('/\R/u', $rulesText) ?: [])
        )
    );

    $startAtRaw = trim((string)($post['start_at'] ?? ''));
    $endAtRaw = trim((string)($post['end_at'] ?? ''));

    if ($startAtRaw === '') {
        throw new InvalidArgumentException('公開開始日時を入力してください。');
    }
    if ($endAtRaw === '') {
        throw new InvalidArgumentException('公開終了日時を入力してください。');
    }

    $startAt = normalize_datetime_input($startAtRaw);
    $endAt = normalize_datetime_input($endAtRaw);

    $startTs = strtotime($startAt);
    $endTs = strtotime($endAt);

    if ($startTs === false) {
        throw new InvalidArgumentException('公開開始日時の形式が正しくありません。');
    }
    if ($endTs === false) {
        throw new InvalidArgumentException('公開終了日時の形式が正しくありません。');
    }
    if ($startTs >= $endTs) {
        throw new InvalidArgumentException('公開終了日時は公開開始日時より後にしてください。');
    }

    $initialDiscountRate = normalize_discount_rate($post['initial_discount_rate'] ?? 0);
    $minDiscountRate = normalize_discount_rate($post['min_discount_rate'] ?? 0);

    if ($initialDiscountRate < 0 || $initialDiscountRate > 1) {
        throw new InvalidArgumentException('初期割引率は 0〜1 の範囲で入力してください。');
    }
    if ($minDiscountRate < 0 || $minDiscountRate > 1) {
        throw new InvalidArgumentException('最低割引率は 0〜1 の範囲で入力してください。');
    }
    if ($minDiscountRate > $initialDiscountRate) {
        throw new InvalidArgumentException('最低割引率は初期割引率以下にしてください。');
    }

    $isActive = !empty($post['is_active']);

    return [
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'product_name' => $productName,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'initial_discount_rate' => round($initialDiscountRate, 4),
        'min_discount_rate' => round($minDiscountRate, 4),

        // 0324仕様での固定値
        'discount_mode' => 'linear',
        'decay_type' => 'daily_linear',

        // 旧仕様項目は互換維持のため残すが、実質未使用
        'decay_interval_minutes' => null,
        'decay_step_rate' => null,

        'is_active' => $isActive,
        'rules' => $rules,
        'notes' => $notes,
    ];
}

/**
 * DB行をアプリ用にデコードする
 */
function decode_plan_row(array $row): array
{
    return [
        'id' => (string)($row['id'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'product_name' => (string)($row['product_name'] ?? ''),
        'start_at' => (string)($row['start_at'] ?? ''),
        'end_at' => (string)($row['end_at'] ?? ''),
        'initial_discount_rate' => normalize_discount_rate($row['initial_discount_rate'] ?? 0),
        'min_discount_rate' => normalize_discount_rate($row['min_discount_rate'] ?? 0),
        'discount_mode' => (string)($row['discount_mode'] ?? 'linear'),
        'decay_type' => (string)($row['decay_type'] ?? 'daily_linear'),
        'decay_interval_minutes' => $row['decay_interval_minutes'] ?? null,
        'decay_step_rate' => isset($row['decay_step_rate']) ? (float)$row['decay_step_rate'] : null,
        'is_active' => (bool)($row['is_active'] ?? false),
        'rules' => decode_jsonish($row['rules'] ?? null),
        'notes' => (string)($row['notes'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

/**
 * 現在有効なプランを1件取得
 */
function find_current_plan(): ?array
{
    $sql = <<<SQL
SELECT *
FROM coupon_plans
WHERE is_active = TRUE
ORDER BY
    updated_at DESC NULLS LAST,
    created_at DESC NULLS LAST,
    id DESC
LIMIT 1
SQL;

    $stmt = db()->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return decode_plan_row($row);
}

/**
 * 全プラン取得
 */
function find_all_plans(): array
{
    $sql = <<<SQL
SELECT *
FROM coupon_plans
ORDER BY
    updated_at DESC NULLS LAST,
    created_at DESC NULLS LAST,
    id DESC
SQL;

    $stmt = db()->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(
        static fn(array $row): array => decode_plan_row($row),
        $rows
    );
}

/**
 * プラン保存
 * 既存IDがあれば更新、なければ新規作成
 */
function save_plan(array $plan): array
{
    $now = now_iso();
    $existing = find_plan_by_id((string)$plan['id']);

    $sql = $existing
        ? <<<SQL
UPDATE coupon_plans
SET
    title = :title,
    description = :description,
    product_name = :product_name,
    start_at = :start_at,
    end_at = :end_at,
    initial_discount_rate = :initial_discount_rate,
    min_discount_rate = :min_discount_rate,
    discount_mode = :discount_mode,
    decay_type = :decay_type,
    decay_interval_minutes = :decay_interval_minutes,
    decay_step_rate = :decay_step_rate,
    is_active = :is_active,
    rules = :rules,
    notes = :notes,
    updated_at = :updated_at
WHERE id = :id
SQL
        : <<<SQL
INSERT INTO coupon_plans (
    id,
    title,
    description,
    product_name,
    start_at,
    end_at,
    initial_discount_rate,
    min_discount_rate,
    discount_mode,
    decay_type,
    decay_interval_minutes,
    decay_step_rate,
    is_active,
    rules,
    notes,
    created_at,
    updated_at
) VALUES (
    :id,
    :title,
    :description,
    :product_name,
    :start_at,
    :end_at,
    :initial_discount_rate,
    :min_discount_rate,
    :discount_mode,
    :decay_type,
    :decay_interval_minutes,
    :decay_step_rate,
    :is_active,
    :rules,
    :notes,
    :created_at,
    :updated_at
)
SQL;

    $params = [
        ':id' => $plan['id'],
        ':title' => $plan['title'],
        ':description' => $plan['description'],
        ':product_name' => $plan['product_name'],
        ':start_at' => $plan['start_at'],
        ':end_at' => $plan['end_at'],
        ':initial_discount_rate' => $plan['initial_discount_rate'],
        ':min_discount_rate' => $plan['min_discount_rate'],
        ':discount_mode' => $plan['discount_mode'],
        ':decay_type' => $plan['decay_type'],
        ':decay_interval_minutes' => $plan['decay_interval_minutes'],
        ':decay_step_rate' => $plan['decay_step_rate'],
        ':is_active' => $plan['is_active'],
        ':rules' => encode_jsonish($plan['rules']),
        ':notes' => $plan['notes'],
        ':updated_at' => $now,
    ];

    if (!$existing) {
        $params[':created_at'] = $now;
    }

    try {
        $stmt = db()->prepare($sql);

        if (!$stmt->execute($params)) {
            throw new RuntimeException('クーポンプランの保存に失敗しました。');
        }
    } catch (Throwable $e) {
        throw new RuntimeException('DB保存エラー: ' . $e->getMessage(), 0, $e);
    }

    return find_plan_by_id((string)$plan['id']) ?? $plan;
}

/**
 * IDでプラン取得
 */
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

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return decode_plan_row($row);
}

/**
 * 線形減衰タイムライン生成
 * 0324仕様:
 * - 公開期間依存
 * - 日単位
 * - 発行前の参考表示用
 */
function generate_discount_timeline(array $plan): array
{
    $startAt = (string)($plan['start_at'] ?? '');
    $endAt = (string)($plan['end_at'] ?? '');

    if ($startAt === '' || $endAt === '') {
        return [];
    }

    $start = to_tokyo_datetime($startAt)->setTime(0, 0, 0);
    $end = to_tokyo_datetime($endAt)->setTime(0, 0, 0);

    if ($end < $start) {
        return [];
    }

    $initial = normalize_discount_rate($plan['initial_discount_rate'] ?? 0);
    $min = normalize_discount_rate($plan['min_discount_rate'] ?? 0);

    if ($min > $initial) {
        $min = $initial;
    }

    $dailyDecayRate = calculate_daily_decay_rate($plan);
    $totalDays = (int)$start->diff($end)->days;

    $timeline = [];

    for ($day = 0; $day <= $totalDays; $day++) {
        $date = $start->modify("+{$day} days");
        $rate = $initial - ($day * $dailyDecayRate);

        if ($rate < $min) {
            $rate = $min;
        }

        if ($rate > $initial) {
            $rate = $initial;
        }

        $timeline[] = [
            'day' => $day,
            'date' => $date->format('Y-m-d'),
            'discount_rate' => round($rate, 4),
            'discount_percent' => round($rate * 100, 2),
        ];
    }

    return $timeline;
}

/**
 * 入力日時を YYYY-MM-DD HH:MM:SS へ寄せる
 */
function normalize_datetime_input(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = str_replace('T', ' ', $value);

    try {
        return to_tokyo_datetime($value)->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return $value;
    }
}

/**
 * 簡易JSONデコード
 */
function decode_jsonish(mixed $value): mixed
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value)) {
        return $value;
    }

    $decoded = json_decode($value, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

/**
 * 簡易JSONエンコード
 */
function encode_jsonish(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_string($value)) {
        return $value;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * プランID生成
 */
function generate_plan_id(): string
{
    return 'plan_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
}