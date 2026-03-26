<?php
declare(strict_types=1);

/**
 * HAYA-TOKU 管理画面（一覧）
 * - プラン一覧表示
 * - 新規作成ページへの導線
 */

// 依存読み込み（ここが重要）
require_once __DIR__ . '/../lib/bootstrap.php';

// 念のため：関数存在チェック（事故防止）
if (!function_exists('find_all_plans')) {
    die('find_all_plans() が未定義です。lib/coupon_model.php の読み込みを確認してください。');
}

// プラン一覧取得
$plans = find_all_plans();
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
        }
        h1 {
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #ff6600;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
        }
        .active {
            color: green;
            font-weight: bold;
        }
        .inactive {
            color: #999;
        }
    </style>
</head>
<body>

<h1>クーポンプラン一覧</h1>

<a href="coupon_edit.php" class="btn">＋ 新規作成</a>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>商品名</th>
            <th>初期割引率</th>
            <th>最小割引率</th>
            <th>状態</th>
            <th>更新日時</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($plans)): ?>
            <tr>
                <td colspan="6">データがありません</td>
            </tr>
        <?php else: ?>
            <?php foreach ($plans as $plan): ?>
                <tr>
                    <td><?= htmlspecialchars($plan['id']) ?></td>
                    <td><?= htmlspecialchars($plan['product_name']) ?></td>
                    <td><?= round($plan['initial_discount_rate'] * 100) ?>%</td>
                    <td><?= round($plan['min_discount_rate'] * 100) ?>%</td>
                    <td>
                        <?php if (!empty($plan['is_active'])): ?>
                            <span class="active">有効</span>
                        <?php else: ?>
                            <span class="inactive">無効</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($plan['updated_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>