<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_admin_login();

$id = trim((string)($_GET['id'] ?? ''));
$plan = $id !== '' ? find_plan_by_id($id) : null;

if (!$plan) {
    $plan = [
        'id' => '',
        'title' => '今日の早得',
        'description' => '公開期間に応じて割引率が変動する早得クーポンです。未使用中も割引率は変動し、利用時点の割引率が最終的に確定します。',
        'is_active' => false,
        'product_name' => '対象商品',
        'start_at' => date('Y-m-d 00:00:00'),
        'end_at' => date('Y-m-d 23:59:59', strtotime('+6 days')),
        'initial_discount_rate' => 0.30,
        'min_discount_rate' => 0.10,
        'rules' => ['店頭でこの画面を提示', '1会計1回まで', '他クーポン併用不可'],
        'notes' => '',
        'created_at' => now_iso(),
    ];
}

$startAtValue = '';
if (!empty($plan['start_at'])) {
    $startAtValue = date('Y-m-d\TH:i', strtotime((string)$plan['start_at']));
}

$endAtValue = '';
if (!empty($plan['end_at'])) {
    $endAtValue = date('Y-m-d\TH:i', strtotime((string)$plan['end_at']));
}

$initialRate = isset($plan['initial_discount_rate'])
    ? (float)$plan['initial_discount_rate']
    : 0.30;

$minRate = isset($plan['min_discount_rate'])
    ? (float)$plan['min_discount_rate']
    : 0.10;

$rulesText = '';
if (!empty($plan['rules']) && is_array($plan['rules'])) {
    $rulesText = implode("\n", $plan['rules']);
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>早得ルール編集 |早得（HAYA-TOKU）（🍊ver / PHP PoC）</title>
  <style>
    body {
      font-family: system-ui, sans-serif;
      background: #fff7ec;
      margin: 0;
      color: #222;
    }
    .wrap {
      max-width: 980px;
      margin: 0 auto;
      padding: 24px;
    }
    .head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }
    .card {
      background: #fff;
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 12px 30px rgba(0,0,0,.08);
      border: 1px solid rgba(255,122,0,.14);
    }
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .full {
      grid-column: 1 / -1;
    }
    label {
      display: block;
      font-size: 13px;
      font-weight: 700;
      margin: 10px 0 6px;
    }
    input, textarea, select {
      width: 100%;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid #ddd;
      font-size: 15px;
      box-sizing: border-box;
    }
    textarea {
      min-height: 120px;
      resize: vertical;
    }
    .actions {
      display: flex;
      gap: 12px;
      margin-top: 20px;
      flex-wrap: wrap;
    }
    button, a.btn {
      display: inline-block;
      padding: 12px 16px;
      border: 0;
      border-radius: 14px;
      font-weight: 800;
      text-decoration: none;
      cursor: pointer;
      box-sizing: border-box;
    }
    .primary {
      background: linear-gradient(180deg, #ff7a00, #ffb300);
      color: #fff;
    }
    .ghost {
      background: #fff;
      border: 1px solid #ddd;
      color: #222;
    }
    .help {
      font-size: 12px;
      color: #666;
      margin-top: 6px;
      line-height: 1.6;
    }
    .note {
      background: #fff3e0;
      border: 1px solid #ffd199;
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 18px;
      line-height: 1.7;
      font-size: 14px;
    }
    .readonly-box {
      background: #fafafa;
      border: 1px dashed #ccc;
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 14px;
      line-height: 1.7;
      color: #444;
    }
    @media (max-width: 720px) {
      .grid {
        grid-template-columns: 1fr;
      }
      .head {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div>
      <h1 style="margin:0">早得ルール編集</h1>
      <div class="help">早得仕様：公開期間依存・日次変動・未使用中は流動・利用時点で割引率確定</div>
    </div>
    <a class="btn ghost" href="/admin/dashboard.php">ダッシュボードへ戻る</a>
  </div>

  <div class="note">
    <strong>この画面の仕様</strong><br>
    ・この画面では、固定割引クーポンではなく、公開期間に応じて割引率が変動する「早得ルール」を設定します。<br>
    ・割引率は発行時に固定されません。未使用の間も、公開期間に応じて日次で変動します。<br>
    ・実際に利用したその瞬間の割引率が最終値として確定します。<br>
    ・時間単位減衰、分単位間隔設定、発行時固定は採用しません。
  </div>

  <form class="card" method="post" action="/admin/coupon_save.php">
    <input type="hidden" name="id" value="<?= h((string)$plan['id']) ?>">

    <div class="grid">
      <div>
        <label for="title">タイトル</label>
        <input id="title" name="title" value="<?= h((string)$plan['title']) ?>" required>
      </div>

      <div>
        <label for="is_active">状態</label>
        <select id="is_active" name="is_active">
          <option value="0" <?= empty($plan['is_active']) ? 'selected' : '' ?>>下書き</option>
          <option value="1" <?= !empty($plan['is_active']) ? 'selected' : '' ?>>公開</option>
        </select>
      </div>

      <div class="full">
        <label for="description">説明文</label>
        <textarea id="description" name="description"><?= h((string)$plan['description']) ?></textarea>
        <div class="help">ユーザー向けの説明文です。早得クーポンの内容や使い方を記載します。</div>
      </div>

      <div>
        <label for="product_name">商品名</label>
        <input id="product_name" name="product_name" value="<?= h((string)($plan['product_name'] ?? '')) ?>">
      </div>

      <div>
        <label for="start_at">公開開始日時</label>
        <input id="start_at" type="datetime-local" name="start_at" value="<?= h($startAtValue) ?>" required>
        <div class="help">この時点が最も高い割引率の開始基準です。</div>
      </div>

      <div>
        <label for="end_at">公開終了日時</label>
        <input id="end_at" type="datetime-local" name="end_at" value="<?= h($endAtValue) ?>" required>
        <div class="help">この時点が最低割引率の到達基準です。</div>
      </div>

      <div>
        <label for="initial_discount_rate">初期割引率（例: 0.30 = 30%）</label>
        <input
          id="initial_discount_rate"
          type="number"
          step="0.0001"
          min="0"
          max="1"
          name="initial_discount_rate"
          value="<?= h((string)$initialRate) ?>"
          required
        >
        <div class="help">公開開始時点の割引率です（最も高い割引率）。</div>
      </div>

      <div>
        <label for="min_discount_rate">最低割引率（例: 0.10 = 10%）</label>
        <input
          id="min_discount_rate"
          type="number"
          step="0.0001"
          min="0"
          max="1"
          name="min_discount_rate"
          value="<?= h((string)$minRate) ?>"
          required
        >
        <div class="help">公開終了時点の割引率です（最も低い割引率）。</div>
      </div>

      <div class="full">
        <label>早得変動ロジック</label>
        <div class="readonly-box">
          公開開始日時から公開終了日時までの期間をもとに、日次で割引率を変動させます。<br>
          クーポンが未使用の間は割引率は固定されず、利用時点の割引率が最終値として確定します。<br>
          現在割引率、残日数、次回変化タイミングは実行ロジック側で自動計算します。
        </div>
      </div>

      <div class="full">
        <label for="rules_text">利用条件（1行1項目）</label>
        <textarea id="rules_text" name="rules_text"><?= h($rulesText) ?></textarea>
      </div>

      <div class="full">
        <label for="notes">備考</label>
        <textarea id="notes" name="notes"><?= h((string)($plan['notes'] ?? '')) ?></textarea>
        <div class="help">管理用メモです。将来の見直しや引き継ぎに使います。</div>
      </div>
    </div>

    <div class="actions">
      <button class="primary" type="submit">保存する</button>
      <a class="btn ghost" href="/public/index.html" target="_blank" rel="noopener noreferrer">フロントを開く</a>
    </div>
  </form>
</div>
</body>
</html>