<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$plan = resolve_current_display_plan();

if (!$plan) {
    $payload = [
        'ok' => false,
        'app' => [
            'documentTitle' => '早得（HAYA-TOKU）（🍊ver / PHP PoC）',
            'displayName' => HAYA_TOKU_APP_NAME,
        ],
        'error' => [
            'code' => 'PLAN_NOT_FOUND',
            'message' => '現在表示可能なクーポンがありません。',
        ],
    ];

    if (function_exists('api_error')) {
        api_error(
            'PLAN_NOT_FOUND',
            '現在表示可能なクーポンがありません。',
            404,
            [
                'app' => $payload['app'],
            ]
        );
    }

    if (function_exists('json_response')) {
        json_response($payload, 404);
    }

    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(404);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$viewModel = build_plan_view_model($plan);
$schedule = $viewModel['schedule'] ?? [];

$response = [
    'ok' => true,
    'app' => [
        'documentTitle' => '早得クーポン | HAYA_TOKU（ver / PHP PoC）',
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
];

if (function_exists('api_success')) {
    api_success($response);
}

if (function_exists('json_response')) {
    json_response($response);
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;