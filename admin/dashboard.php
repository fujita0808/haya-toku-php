<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin_login();

$plans = find_all_plans();
$logs = [];
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ダッシュボード | HAYA-TOKU（🍊ver / PHP PoC）</title>
  <style>
    body{font-family:system-ui,sans-serif;background:#fff7ec;margin:0;color:#222}
    .wrap{max-width:1100px;margin:0 auto;padding:24px}
    .top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
    .card{background:#fff;border-radius:20px;padding:20px;box-shadow:0 12px 30px rgba(0,0,0,.08);border:1px solid rgba(255,122,0,.14);margin-bottom:18px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eee;font-size:14px;vertical-align:top;text-align:left}
    .btn{display:inline-block;padding:10px 14px;border-radius:12px;text-decoration:none;font-weight:800}
    .primary{background:linear-gradient(180deg,#ff7a00,#ffb300);color:#fff}
    .muted{color:#666;font-size:13px}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#f7f7f7;border:1px solid #eee;font-size:12px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <h1 style="margin:0">HAYA-TOKU ダッシュボード（🍊ver / PHP PoC）</h1>
      <div class="muted">管理画面 / PostgreSQL移行対応中</div>
    </div>
    <div>
      <a class="btn primary" href="/admin/coupon_edit.php">新規クーポン作成</a>
      <a class="btn" href="/admin/logout.php">ログアウト</a>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0">クーポンプラン一覧</h2>
    <table>
      <thead><tr><th>タイトル</th><th>商品</th><th>割引</th><th>状態</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($plans as $plan): ?>
        <tr>
          <td><strong><?= h($plan['title']) ?></strong><br><span class="muted"><?= h($plan['description']) ?></span></td>
          <td><?= h($plan['product_name']) ?></td>
          <td><?= h((string)round(((float)$plan['initial_discount_rate']) * 100, 1)) ?>% → <?= h((string)round(((float)$plan['min_discount_rate']) * 100, 1)) ?>%</td>
          <td><span class="pill"><?= !empty($plan['is_active']) ? '公開' : '下書き' ?></span></td>
          <td><a href="/admin/coupon_edit.php?id=<?= urlencode($plan['id']) ?>">編集</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 style="margin-top:0">利用ログ（最新20件）</h2>
    <table>
      <thead><tr><th>日時</th><th>クーポン</th><th>ユーザー</th><th>割引率</th><th>割引後価格</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse(array_slice($logs, -20)) as $log): ?>
        <tr>
          <td><?= h($log['used_at']) ?></td>
          <td><?= h($log['coupon_plan_id']) ?></td>
          <td><?= h($log['display_name']) ?><br><span class="muted"><?= h($log['user_id']) ?></span></td>
          <td><?= h((string)round(((float)$log['discount_rate']) * 100, 1)) ?>%</td>
          <td><?= h((string)$log['discounted_price']) ?>円</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>