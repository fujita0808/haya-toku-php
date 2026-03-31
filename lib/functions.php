<?php

declare(strict_types=1);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function post_string(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function post_int(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

function post_float(string $key, float $default = 0.0): float
{
    return (float)($_POST[$key] ?? $default);
}

function now_iso(): string
{
    return date('c');
}

function to_datetime_local(string $value): string
{
    $ts = strtotime($value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}
function api_success(array $data = [], int $status = 200): never
{
    json_response([
        'ok' => true,
        'data' => $data,
    ], $status);
}
function api_error(string $code, string $message, int $status = 400, array $details = []): never
{
    $payload = [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ];

    if ($details !== []) {
        $payload = array_merge($payload, $details);
    }

    json_response($payload, $status);
}