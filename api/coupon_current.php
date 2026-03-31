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

$plan = find_current_plan();

if ($plan === null) {
    api_error('PLAN_NOT_FOUND', '現在公開中の早得クーポンがありません。', 404, [
        'app' => [
            'documentTitle' => '早得クーポン | ' . HAYA_TOKU_APP_NAME,
            'displayName' => HAYA_TOKU_APP_NAME,
        ],
    ]);
}

$viewModel = build_plan_view_model($plan);
$schedule = $viewModel['schedule'] ?? [];

api_success([
    'app' => [
        'documentTitle' => '早得クーポン | ' . HAYA_TOKU_APP_NAME,
        'displayName' => HAYA_TOKU_APP_NAME,
    ],
    'plan' => [
        'id' => (string)$viewModel['id'],
        'title' => (string)$viewModel['title'],
        'description' => (string)$viewModel['description'],
        'product_name' => (string)$viewModel['product_name'],
        'status_code' => (string)$viewModel['status_code'],
        'status_label' => (string)$viewModel['status_label'],
        'is_active' => (bool)$viewModel['is_active'],
        'start_at' => (string)$viewModel['start_at'],
        'end_at' => (string)$viewModel['end_at'],
        'initial_discount_rate' => (float)$viewModel['initial_discount_rate'],
        'min_discount_rate' => (float)$viewModel['min_discount_rate'],
        'rules' => is_array($viewModel['rules']) ? $viewModel['rules'] : [],
        'notes' => (string)$viewModel['notes'],
    ],
    'schedule' => [
        'status' => (string)($schedule['status'] ?? ''),
        'is_active_now' => (bool)($schedule['is_active_now'] ?? false),
        'current_discount_rate' => (float)($schedule['current_discount_rate'] ?? 0),
        'initial_discount_rate' => (float)($schedule['initial_discount_rate'] ?? 0),
        'min_discount_rate' => (float)($schedule['min_discount_rate'] ?? 0),
        'total_days' => (int)($schedule['total_days'] ?? 0),
        'elapsed_days' => (int)($schedule['elapsed_days'] ?? 0),
        'remaining_days' => (int)($schedule['remaining_days'] ?? 0),
        'progress_ratio' => (float)($schedule['progress_ratio'] ?? 0),
        'next_change_at' => $schedule['next_change_at'] ?? null,
    ],
], 200);