<?php

declare(strict_types=1);

function ensure_storage_file(string $filename, array $default): string
{
    $path = HAYA_TOKU_STORAGE_DIR . '/' . $filename;
    if (!is_dir(HAYA_TOKU_STORAGE_DIR)) {
        mkdir(HAYA_TOKU_STORAGE_DIR, 0777, true);
    }
    if (!file_exists($path)) {
        file_put_contents($path, json_encode($default, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    return $path;
}

function read_json_file(string $filename, array $default = []): array
{
    $path = ensure_storage_file($filename, $default);
    $raw = file_get_contents($path);
    $decoded = json_decode($raw ?: '[]', true);
    return is_array($decoded) ? $decoded : $default;
}

function write_json_file(string $filename, array $data): void
{
    $path = ensure_storage_file($filename, []);
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function load_coupon_plans(): array
{
    return read_json_file('coupon_plans.json', ['plans' => []]);
}

function save_coupon_plans(array $plans): void
{
    write_json_file('coupon_plans.json', ['plans' => array_values($plans)]);
}

function load_usage_logs(): array
{
    return read_json_file('usage_logs.json', ['logs' => []]);
}

function append_usage_log(array $log): void
{
    $data = load_usage_logs();
    $data['logs'][] = $log;
    write_json_file('usage_logs.json', $data);
}
