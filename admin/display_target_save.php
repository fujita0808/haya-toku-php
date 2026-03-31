<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_admin_login();

if (request_method() !== 'POST') {
    redirect_to('/admin/dashboard.php');
}

/**
 * 現在表示可能なプランか判定
 * dashboard の考え方と揃える
 */
function can_display_now(array $plan, ?int $nowTs = null): bool
{
    $nowTs ??= time();

    if (empty($plan['is_active'])) {
        return false;
    }

    $startTs = strtotime((string)($plan['start_at'] ?? ''));
    $endTs   = strtotime((string)($plan['end_at'] ?? ''));

    if ($startTs === false || $endTs === false) {
        return false;
    }

    return $startTs <= $nowTs && $endTs >= $nowTs;
}

try {
    $planId = trim((string)($_POST['display_plan_id'] ?? ''));

    if ($planId === '') {
        save_display_target_plan_id(null);
        redirect_to('/admin/dashboard.php');
    }

    $plan = find_plan_by_id($planId);
    if ($plan === null) {
        throw new RuntimeException('指定されたプランが見つかりません。');
    }

    if (!can_display_now($plan)) {
        throw new RuntimeException('このプランは現在表示対象にできません。公開中のプランのみ選択できます。');
    }

    save_display_target_plan_id($planId);
    redirect_to('/admin/dashboard.php');

} catch (Throwable $e) {
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>保存エラー | <?= h(HAYA_TOKU_APP_NAME) ?></title>
      <style>
        body {
          font-family: system-ui, sans-serif;
          background: #fff7ec;
          color: #222;
          margin: 0;
          padding: 24px;
        }
        .card {
          max-width: 720px;
          margin: 40px auto;
          background: #fff;
          border-radius: 16px;
          padding: 24px;
          border: 1px solid #ffd199;
          box-shadow: 0 10px 24px rgba(0,0,0,.08);
        }
        .error {
          color: #b00020;
          font-weight: 700;
          margin-top: 12px;
          line-height: 1.7;
        }
        a {
          display: inline-block;
          margin-top: 16px;
          text-decoration: none;
          font-weight: 700;
          color: #d46600;
        }
      </style>
    </head>
    <body>
      <div class="card">
        <h1 style="margin-top:0;">表示対象の保存に失敗しました</h1>
        <p>選択内容または保存処理に問題があります。</p>
        <div class="error"><?= h($e->getMessage()) ?></div>
        <a href="javascript:history.back()">管理画面に戻る</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}