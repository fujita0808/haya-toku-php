<?php
declare(strict_types=1);

/**
 * HAYA-TOKU 管理画面（一覧）
 * - プラン一覧表示
 * - 新規作成ページへの導線
 * - 状態表示（公開中 / 公開前 / 期限切れ / 無効）
 * - 編集ページへの導線
 */

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('find_all_plans')) {
    die('find_all_plans() が未定義です。lib 側の読み込みを確認してください。');
}

/**
 * DB値を DateTimeImmutable に変換
 */
function to_datetime_or_null(mixed $value): ?DateTimeImmutable
{
    if ($value instanceof DateTimeImmutable) {
        return $value;
    }

    if ($value instanceof DateTimeInterface) {
        return new DateTimeImmutable($value->format(DateTimeInterface::ATOM));
    }

    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * プラン状態コードを返す
 * - active   : 公開中
 * - upcoming : 公開前
 * - expired  : 期限切れ
 * - inactive : 無効
 */
function plan_status_code(array $plan, ?DateTimeImmutable $now = null): string
{
    $now = $now ?? new DateTimeImmutable('now');
    $isActive = !empty($plan['is_active']);

    if (!$isActive) {
        return 'inactive';
    }

    $startAt = to_datetime_or_null($plan['start_at'] ?? null);
    $endAt   = to_datetime_or_null($plan['end_at'] ?? null);

    if ($startAt !== null && $now < $startAt) {
        return 'upcoming';
    }

    if ($endAt !== null && $now > $endAt) {
        return 'expired';
    }

    return 'active';
}

/**
 * 状態コードから表示名を返す
 */
function plan_status_label(string $statusCode): string
{
    return match ($statusCode) {
        'active'   => '公開中',
        'upcoming' => '公開前',
        'expired'  => '期限切れ',
        default    => '無効',
    };
}

/**
 * 状態コードから CSS class を返す
 */
function plan_status_class(string $statusCode): string
{
    return 'status-' . $statusCode;
}

/**
 * 表示用日時
 */
function format_datetime_value(mixed $value): string
{
    $dt = to_datetime_or_null($value);
    if ($dt === null) {
        return '';
    }

    return $dt->format('Y-m-d H:i');
}

$plans = find_all_plans();
$now = new DateTimeImmutable('now');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理画面 | HAYA-TOKU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            background: #fff;
            color: #222;
        }

        h1 {
            margin-bottom: 20px;
        }

        .toolbar {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #ff6600;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            font-size: 14px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-edit {
            background: #444;
            padding: 6px 12px;
            font-size: 13px;
        }

        .now-info {
            margin-bottom: 12px;
            color: #666;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px 8px;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f5f5f5;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-active {
            color: #0a7a2f;
            background: #e8f7ed;
        }

        .status-upcoming {
            color: #1f5fbf;
            background: #eaf2ff;
        }

        .status-expired {
            color: #c62828;
            background: #fdecec;
        }

        .status-inactive {
            color: #666;
            background: #f0f0f0;
        }

        .col-actions {
            white-space: nowrap;
            width: 110px;
        }

        .empty {
            color: #666;
        }
    </style>
</head>
<body>

<h1>クーポンプラン一覧</h1>

<div class="toolbar">
    <a href="coupon_edit.php" class="btn">＋ 新規作成</a>
</div>

<div class="now-info">
    判定時刻: <?= htmlspecialchars($now->format('Y-m-d H:i:s')) ?>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>商品名</th>
            <th>初期割引率</th>
            <th>最小割引率</th>
            <th>開始日時</th>
            <th>終了日時</th>
            <th>状態</th>
            <th>更新日時</th>
            <th class="col-actions">操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($plans)): ?>
            <tr>
                <td colspan="9" class="empty">データがありません</td>
            </tr>
        <?php else: ?>
            <?php foreach ($plans as $plan): ?>
                <?php
                $statusCode = plan_status_code($plan, $now);
                $statusLabel = plan_status_label($statusCode);
                $statusClass = plan_status_class($statusCode);
                $planId = (string)($plan['id'] ?? '');
                ?>
                <tr>
                    <td><?= htmlspecialchars($planId) ?></td>
                    <td><?= htmlspecialchars((string)($plan['product_name'] ?? '')) ?></td>
                    <td><?= isset($plan['initial_discount_rate']) ? round((float)$plan['initial_discount_rate'] * 100) . '%' : '' ?></td>
                    <td><?= isset($plan['min_discount_rate']) ? round((float)$plan['min_discount_rate'] * 100) . '%' : '' ?></td>
                    <td><?= htmlspecialchars(format_datetime_value($plan['start_at'] ?? null)) ?></td>
                    <td><?= htmlspecialchars(format_datetime_value($plan['end_at'] ?? null)) ?></td>
                    <td>
                        <span class="status-badge <?= htmlspecialchars($statusClass) ?>">
                            <?= htmlspecialchars($statusLabel) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(format_datetime_value($plan['updated_at'] ?? null)) ?></td>
                    <td class="col-actions">
                        <a
                            href="coupon_edit.php?id=<?= urlencode($planId) ?>"
                            class="btn btn-edit"
                        >編集</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>