<?php
$readmePath = __DIR__ . '/README.md';

if (!is_readable($readmePath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'README.md not found.';
    exit;
}

$readme = file_get_contents($readmePath);

if ($readme === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Failed to load README.md.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>README.md</title>
  <style>
    :root {
      color-scheme: light;
    }

    body {
      margin: 0;
      background: #f7f4ed;
      color: #1f1f1f;
      font-family: "Hiragino Sans", "Yu Gothic", sans-serif;
    }

    main {
      max-width: 960px;
      margin: 0 auto;
      padding: 40px 20px 64px;
    }

    h1 {
      margin: 0 0 12px;
      font-size: 28px;
    }

    p {
      margin: 0 0 24px;
      color: #5d5348;
    }

    pre {
      margin: 0;
      padding: 24px;
      border: 1px solid #d7cfc3;
      border-radius: 12px;
      background: #fffdf8;
      white-space: pre-wrap;
      word-break: break-word;
      line-height: 1.7;
      overflow-x: auto;
    }
  </style>
</head>
<body>
  <main>
    <h1>README.md</h1>
    <p>このページはリポジトリ直下の README.md をそのまま表示しています。</p>
    <pre><?= htmlspecialchars($readme, ENT_QUOTES, 'UTF-8') ?></pre>
  </main>
</body>
</html>
