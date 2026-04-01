<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

$plan = resolve_current_display_plan();

if (!$plan) {
    $payload = [
        'ok' => false,
        'app' => [
            'documentTitle' => '早得クーポン | HAYA-TOKU（ver / PHP PoC）',
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

$response = [
    'ok' => true,
    'app' => [
        'documentTitle' => '早得クーポン | HAYA-TOKU（ver / PHP PoC）',
        'displayName' => HAYA_TOKU_APP_NAME,
    ],
    'plan' => $plan,
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