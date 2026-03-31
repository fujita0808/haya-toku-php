<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

require_admin_login();

if (request_method() !== 'POST') {
    redirect_to('/admin/dashboard.php');
}

try {
    // ★① boolean補正（ここで確定させる）
    $_POST['is_active'] = isset($_POST['is_active']) && $_POST['is_active'] == '1';

    $plan = normalize_plan_from_post($_POST);

    // ★② active一意制御
    if (!empty($plan['is_active'])) {
        db()->exec("UPDATE coupon_plans SET is_active = false");
    }

    save_plan($plan);

    redirect_to('/admin/dashboard.php');

} catch (Throwable $e) {
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>保存エラー | 早得（HAYA-TOKU）（🍊ver / PHP PoC）</title>
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
        <h1 style="margin-top:0;">保存に失敗しました</h1>
        <p>入力内容または保存処理に問題があります。</p>
        <div class="error"><?= h($e->getMessage()) ?></div>
        <a href="javascript:history.back()">入力画面に戻る</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}