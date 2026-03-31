<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (function_exists('send_api_headers')) {
        send_api_headers();
    }
    http_response_code(200);
    exit;
}

if (function_exists('send_api_headers')) {
    send_api_headers();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('METHOD_NOT_ALLOWED', 'GET メソッドでアクセスしてください。', 405);
}

try {
    $pdo = db();
    $stmt = $pdo->query('SELECT NOW() as now');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    api_success([
        'message' => 'health check ok',
        'db_time' => $row['now'] ?? null,
    ], 200);
} catch (Throwable $e) {
    api_error('HEALTH_CHECK_FAILED', 'ヘルスチェックに失敗しました。', 500, [
        'debug' => [
            'message' => $e->getMessage(),
        ],
    ]);
}