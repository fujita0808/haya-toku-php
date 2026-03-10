<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_admin_login();
$id = trim((string)($_GET['id'] ?? ''));
$plan = $id !== '' ? find_plan_by_id($id) : null;
if (!$plan) {
    $plan = [
        'id' => '',
        'title' => '今日の早得',
        'description' => '開始直後ほど割引率が高く、時間経過で最低割引率まで減衰します。',
        'status' => 'draft',
        'product_name' => '対象商品',
        'unit_price' => 1200,
        'cost_rate' => 0.35,
        'initial_discount_rate' => 0.30,
        'min_discount_rate' => 0.10,
        'decay_interval_minutes' => 180,
        'start_at' => date('Y-m-d 00:00:00'),
        'end_at' => date('Y-m-d 23:59:59'),
        'target_revenue' => 100000,
        'rules' => ['店頭でこの画面を提示','1会計1回まで','他クーポン併用不可'],
        'notes' => '',
        'created_at' => now_iso(),
    ];
}
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>クーポン編集 | HAYA-TOKU</title>
  <style>
    body{font-family:system-ui,sans-serif;background:#fff7ec;margin:0;color:#222}
    .wrap{max-width:980px;margin:0 auto;padding:24px}
    .card{background:#fff;border-radius:20px;padding:24px;box-shadow:0 12px 30px rgba(0,0,0,.08);border:1px solid rgba(255,122,0,.14)}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    label{display:block;font-size:13px;font-weight:700;margin:10px 0 6px}
    input,textarea,select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #ddd;font-size:15px}
    textarea{min-height:120px}
    .full{grid-column:1 / -1}
    .actions{display:flex;gap:12px;margin-top:20px}
    button,a{display:inline-block;padding:12px 16px;border:0;border-radius:14px;font-weight:800;text-decoration:none;cursor:pointer}
    .primary{background:linear-gradient(180deg,#ff7a00,#ffb300);color:#fff}
    .ghost{background:#fff;border:1px solid #ddd;color:#222}
    .help{font-size:12px;color:#666;margin-top:6px}
  </style>
</head>
<body>
<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px">
    <div><h1 style="margin:0">クーポン編集</h1><div class="help">PoC 仕様: 初期割引 → 減衰 → 最低割引</div></div>
    <a class="ghost" href="/admin/dashboard.php">ダッシュボードへ戻る</a>
  </div>

  <form class="card" method="post" action="/admin/coupon_save.php">
    <input type="hidden" name="id" value="<?= h($plan['id']) ?>">
    <input type="hidden" name="created_at" value="<?= h($plan['created_at']) ?>">
    <div class="grid">
      <div>
        <label>タイトル</label>
        <input name="title" value="<?= h($plan['title']) ?>" required>
      </div>
      <div>
        <label>状態</label>
        <select name="status">
          <option value="draft" <?= $plan['status']==='draft'?'selected':'' ?>>draft</option>
          <option value="active" <?= $plan['status']==='active'?'selected':'' ?>>active</option>
        </select>
      </div>
      <div class="full">
        <label>説明文</label>
        <textarea name="description"><?= h($plan['description']) ?></textarea>
      </div>
      <div>
        <label>商品名</label>
        <input name="product_name" value="<?= h($plan['product_name']) ?>">
      </div>
      <div>
        <label>商品単価（円）</label>
        <input type="number" name="unit_price" value="<?= h((string)$plan['unit_price']) ?>">
      </div>
      <div>
        <label>原価率（0〜1）</label>
        <input type="number" step="0.01" name="cost_rate" value="<?= h((string)$plan['cost_rate']) ?>">
      </div>
      <div>
        <label>売上目標（円）</label>
        <input type="number" name="target_revenue" value="<?= h((string)$plan['target_revenue']) ?>">
      </div>
      <div>
        <label>初期割引率（例 0.30）</label>
        <input type="number" step="0.01" name="initial_discount_rate" value="<?= h((string)$plan['initial_discount_rate']) ?>">
      </div>
      <div>
        <label>最低割引率（例 0.10）</label>
        <input type="number" step="0.01" name="min_discount_rate" value="<?= h((string)$plan['min_discount_rate']) ?>">
      </div>
      <div>
        <label>減衰間隔（分）</label>
        <input type="number" name="decay_interval_minutes" value="<?= h((string)$plan['decay_interval_minutes']) ?>">
      </div>
      <div>
        <label>開始日時</label>
        <input type="datetime-local" name="start_at" value="<?= h(to_datetime_local($plan['start_at'])) ?>">
      </div>
      <div>
        <label>終了日時</label>
        <input type="datetime-local" name="end_at" value="<?= h(to_datetime_local($plan['end_at'])) ?>">
      </div>
      <div class="full">
        <label>利用条件（1行1項目）</label>
        <textarea name="rules_text"><?= h(implode("\n", $plan['rules'])) ?></textarea>
      </div>
      <div class="full">
        <label>備考</label>
        <textarea name="notes"><?= h($plan['notes']) ?></textarea>
      </div>
    </div>
    <div class="actions">
      <button class="primary" type="submit">保存する</button>
      <a class="ghost" href="/public/kore.html" target="_blank">フロントを開く</a>
    </div>
  </form>
</div>
</body>
</html>
