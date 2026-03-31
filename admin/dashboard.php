<?php
declare(strict_types=1);

/**
 * HAYA-TOKU 管理画面（一覧）
 * - プラン一覧表示
 * - 新規作成ページへの導線
 * - 編集ページへの導線
 * - 状態表示（coupon_logic.php の共通判定を使用）
 */

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('find_all_plans')) {
    die('find_all_plans() が未定義です。lib/db.php の読み込みを確認してください。');
}

if (!function_exists('build_plan_view_model')) {
    die('build_plan_view_model() が未定義です。lib/coupon_logic.php の読み込みを確認してください。');
}

function format_datetime_value(mixed $value): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $ts = strtotime($text);
    if ($ts === false) {
        return $text;
    }

    return date('Y-m-d H:i', $ts);
}

function status_badge_class(string $statusCode): string
{
    return match ($statusCode) {
        'active' => 'status-active',
        'scheduled' => 'status-scheduled',
        'ended' => 'status-ended',
        'draft' => 'status-draft',
        default => 'status-invalid',
    };
}

$plans = find_all_plans();
$nowTs = time();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理画面 | <?= h(HAYA_TOKU_APP_NAME) ?></title>
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

        .status-scheduled {
            color: #1f5fbf;
            background: #eaf2ff;
        }

        .status-ended {
            color: #c62828;
            background: #fdecec;
        }

        .status-draft {
            color: #666;
            background: #f0f0f0;
        }

        .status-invalid {
            color: #8a2be2;
            background: #f4ecff;
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
    判定時刻: <?= h(date('Y-m-d H:i:s', $nowTs)) ?>
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
                $view = build_plan_view_model($plan, $nowTs);
                $planId = (string)($view['id'] ?? '');
                $statusCode = (string)($view['status_code'] ?? 'invalid');
                $statusLabel = (string)($view['status_label'] ?? '設定不正');
                ?>
                <tr>
                    <td><?= h($planId) ?></td>
                    <td><?= h((string)($view['product_name'] ?? '')) ?></td>
                    <td><?= round((float)($view['initial_discount_rate'] ?? 0) * 100) ?>%</td>
                    <td><?= round((float)($view['min_discount_rate'] ?? 0) * 100) ?>%</td>
                    <td><?= h(format_datetime_value($view['start_at'] ?? '')) ?></td>
                    <td><?= h(format_datetime_value($view['end_at'] ?? '')) ?></td>
                    <td>
                        <span class="status-badge <?= h(status_badge_class($statusCode)) ?>">
                            <?= h($statusLabel) ?>
                        </span>
                    </td>
                    <td><?= h(format_datetime_value($plan['updated_at'] ?? '')) ?></td>
                    <td class="col-actions">
                        <a href="coupon_edit.php?id=<?= urlencode($planId) ?>" class="btn btn-edit">編集</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>