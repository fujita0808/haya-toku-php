<?php
require_once __DIR__ . '/../lib/bootstrap.php';

if (is_admin_logged_in()) {
    redirect_to('/admin/dashboard.php');
}

$error = '';
if (request_method() === 'POST') {
    $loginId = post_string('login_id');
    $password = post_string('password');
    if (attempt_login($loginId, $password)) {
        redirect_to('/admin/dashboard.php');
    }
    $error = 'ログインに失敗しました。';
}
?><!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>管理画面ログイン | HAYA-TOKU</title>
  <style>
    body{font-family:system-ui,sans-serif;background:#fff7ec;margin:0;color:#222}
    .wrap{max-width:420px;margin:8vh auto;background:#fff;padding:24px;border-radius:20px;box-shadow:0 18px 40px rgba(0,0,0,.08);border:1px solid rgba(255,122,0,.15)}
    h1{font-size:22px;margin:0 0 8px} .sub{color:#666;margin-bottom:20px}
    label{display:block;font-size:13px;font-weight:700;margin:14px 0 6px}
    input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #ddd;font-size:15px}
    button{margin-top:18px;width:100%;padding:14px;border:0;border-radius:14px;font-weight:800;background:linear-gradient(180deg,#ff7a00,#ffb300);color:#fff;cursor:pointer}
    .err{margin:10px 0;color:#b42318;font-size:14px}
    .note{margin-top:16px;font-size:12px;color:#666;background:#fff7ec;padding:12px;border-radius:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>HAYA-TOKU 管理画面</h1>
    <div class="sub">PoC 用の簡易ログインです。</div>
    <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <label for="login_id">ログインID</label>
      <input id="login_id" name="login_id" value="admin" required>
      <label for="password">パスワード</label>
      <input id="password" type="password" name="password" value="hayatoku123" required>
      <button type="submit">ログイン</button>
    </form>
    <div class="note">初期値は README に記載しています。PoC なので `storage/admins.php` で差し替えてください。</div>
  </div>
</body>
</html>
