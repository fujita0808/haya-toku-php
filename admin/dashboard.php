<?php

declare(strict_types=1);

/**
 * HAYA-TOKU 管理画面（一覧）
 * - プラン一覧表示
 * - 新規作成ページへの導線
 * - 編集ページへの導線
 * - 現在状態表示（coupon_logic.php の状態判定を利用）
 * - フロント表示対象の選択（公開中のみ選択可）
 */

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('find_all_plans')) {
    die('find_all_plans() が未定義です。lib/db.php の読み込みを確認してください。');
}

if (!function_exists('plan_status_code') || !function_exists('plan_status_label')) {
    die('plan_status_code() / plan_status_label() が未定義です。lib/coupon_logic.php の読み込みを確認してください。');
}

$plans = find_all_plans();
$nowTs = time();

/**
 * HTMLエスケープ
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * 日時文字列を timestamp に変換
 */
function to_timestamp_or_null(mixed $value): ?int
{
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }

    $ts = strtotime($text);
    return $ts === false ? null : $ts;
}

/**
 * 状態バッジ用 class
 * 業務状態コードを UI 用 class に変換する
 */
function dashboard_status_class(string $statusCode): string
{
    return match ($statusCode) {
        'active' => 'status-active',
        'scheduled' => 'status-scheduled',
        'ended' => 'status-ended',
        'draft' => 'status-draft',
        default => 'status-invalid',
    };
}

/**
 * 表示用日時
 */
function format_datetime_value(mixed $value): string
{
    $ts = to_timestamp_or_null($value);
    if ($ts === null) {
        return '';
    }

    return date('Y-m-d H:i', $ts);
}

/**
 * 現在選択中の表示対象ID
 * - get_display_target_plan_id() を利用
 * - なければ未選択扱い
 */
$selectedPlanId = null;
if (function_exists('get_display_target_plan_id')) {
    $selectedPlanId = get_display_target_plan_id();
    if ($selectedPlanId !== null) {
        $selectedPlanId = (string)$selectedPlanId;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>管理画面 |早得（HAYA-TOKU）（🍊ver / PHP PoC）</title>
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
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
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
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-edit {
            background: #444;
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-sub {
            background: #666;
        }

        .btn-front {
            background: #666;
            padding: 6px 12px;
            font-size: 13px;
            margin-left: 6px;
        }

        .now-info {
            margin-bottom: 12px;
            color: #666;
            font-size: 13px;
        }

        .hint {
            margin-bottom: 16px;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }

        .selection-box {
            margin-bottom: 16px;
            padding: 12px;
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
        }

        .selection-box .title {
            font-weight: bold;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
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
            color: #7a3db8;
            background: #f3ebff;
        }

        .select-cell {
            text-align: center;
            width: 72px;
        }

        .select-cell input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .row-disabled {
            background: #fafafa;
            color: #888;
        }

        .row-disabled input[type="radio"] {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .col-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }

        .col-actions {
            white-space: nowrap;
            width: 110px;
        }

        .empty {
            color: #666;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            min-width: 1100px;
            width: auto;
        }
    </style>
</head>

<body>

    <h1>クーポンプラン一覧</h1>

    <form method="post" action="display_target_save.php" id="display-target-form">
        <div class="toolbar">
            <a href="coupon_edit.php" class="btn">＋ 新規作成</a>
            <button type="submit" class="btn">表示対象を保存</button>
            <button type="button" class="btn btn-sub" id="clear-selection-btn">選択解除</button>
            <button type="button" class="btn btn-front" id="open-front-btn">フロント表示</button>
        </div>

        <div class="selection-box">
            <div class="title">フロント表示対象の選択</div>
            <div class="hint">
                公開中のプランだけ選択できます。公開前・終了・下書き・設定不正は選択できません。<br>
                「選択解除」を押すと未選択のまま保存できます。
            </div>
            <input type="hidden" name="display_plan_id" id="display_plan_id" value="<?= e($selectedPlanId ?? '') ?>">
        </div>

        <div class="now-info">
            判定時刻: <?= e(date('Y-m-d H:i:s', $nowTs)) ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="select-cell">表示</th>
                        <th>商品名</th>
                        <th>タイトル</th>
                        <th>説明文</th>
                        <th>状態</th>
                        <th>開始日時</th>
                        <th>終了日時</th>
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
                            $planId = (string)($plan['id'] ?? '');
                            $statusCode = plan_status_code($plan, $nowTs);
                            $statusLabel = plan_status_label($plan, $nowTs);
                            $statusClass = dashboard_status_class($statusCode);
                            $selectable = plan_can_be_selected_for_front($plan, $nowTs);
                            $checked = ($selectedPlanId !== null && $selectedPlanId === $planId);
                            ?>
                            <tr class="<?= $selectable ? '' : 'row-disabled' ?>">
                                <td class="select-cell">
                                    <input
                                        type="radio"
                                        name="display_plan_id_radio"
                                        value="<?= e($planId) ?>"
                                        <?= $checked ? 'checked' : '' ?>
                                        <?= $selectable ? '' : 'disabled' ?>>
                                </td>
                                <td><?= e((string)($plan['product_name'] ?? '')) ?></td>
                                <td><?= e((string)($plan['title'] ?? '')) ?></td>
                                <td><?= e((string)($plan['description'] ?? '')) ?></td>
                                <td>
                                    <span class="status-badge <?= e($statusClass) ?>">
                                        <?= e($statusLabel) ?>
                                    </span>
                                </td>
                                <td><?= e(format_datetime_value($plan['start_at'] ?? '')) ?></td>
                                <td><?= e(format_datetime_value($plan['end_at'] ?? '')) ?></td>
                                <td><?= e(format_datetime_value($plan['updated_at'] ?? '')) ?></td>
                                <td class="col-actions">
                                    <a href="coupon_edit.php?id=<?= urlencode($planId) ?>" class="btn btn-edit">編集</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <script>
        (() => {
            const hiddenInput = document.getElementById('display_plan_id');
            const radios = document.querySelectorAll('input[name="display_plan_id_radio"]');
            const clearBtn = document.getElementById('clear-selection-btn');

            function syncHiddenFromChecked() {
                const checked = document.querySelector('input[name="display_plan_id_radio"]:checked');
                hiddenInput.value = checked ? checked.value : '';
            }

            radios.forEach((radio) => {
                radio.addEventListener('change', syncHiddenFromChecked);
            });

            clearBtn.addEventListener('click', () => {
                radios.forEach((radio) => {
                    radio.checked = false;
                });
                hiddenInput.value = '';
            });

            syncHiddenFromChecked();


            const openFrontBtn = document.getElementById('open-front-btn');

            openFrontBtn.addEventListener('click', () => {
                const checked = document.querySelector('input[name="display_plan_id_radio"]:checked');

                if (!checked) {
                    alert('表示させるクーポンを選択してください！');
                    return;
                }

                window.open('/public/index.html', '_blank');
            });
        })();
    </script>

</body>

</html>