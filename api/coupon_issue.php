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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('METHOD_NOT_ALLOWED', 'POST メソッドでアクセスしてください。', 405);
}

/**
 * 入力取得
 * 優先:
 * 1. JSON body
 * 2. form-urlencoded / multipart
 */
$rawBody = file_get_contents('php://input');
$input = [];

if (is_string($rawBody) && trim($rawBody) !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

if ($input === []) {
    $input = $_POST;
}

$planId = trim((string)($input['plan_id'] ?? ''));
$userId = trim((string)($input['user_id'] ?? ''));
$notes = trim((string)($input['notes'] ?? ''));

if ($planId === '') {
    $currentPlan = find_current_plan();
    if ($currentPlan !== null) {
        $planId = (string)($currentPlan['id'] ?? '');
    }
}

if ($planId === '') {
    api_error('PLAN_ID_REQUIRED', 'plan_id が必要です。', 400);
}

$plan = find_plan_by_id($planId);
if ($plan === null) {
    api_error('PLAN_NOT_FOUND', '対象プランが見つかりません。', 404);
}

if (!plan_is_available_now($plan)) {
    $statusCode = plan_status_code($plan);

    api_error(strtoupper($statusCode), '現在この早得クーポンは発行できません。', 400, [
        'plan' => [
            'id' => (string)($plan['id'] ?? ''),
            'status_code' => $statusCode,
            'status_label' => plan_status_label($plan),
        ],
    ]);
}

$couponColumns = coupon_table_columns();
if ($couponColumns === []) {
    api_error('COUPONS_TABLE_NOT_FOUND', 'coupons テーブルが見つかりません。', 500);
}

$issuedAtTs = time();
$issuedAt = date('Y-m-d H:i:s', $issuedAtTs);

/**
 * 発行時点の割引率は参考値として保存する
 * 最終的な適用割引率は利用時に確定する
 */
$issuedDiscountRate = schedule_current_discount_rate($plan, $issuedAtTs);

/**
 * クーポンコード生成
 */
$maxAttempts = 10;
$couponCode = null;

for ($i = 0; $i < $maxAttempts; $i++) {
    $candidate = 'HT' . strtoupper(bin2hex(random_bytes(4)));
    $exists = find_coupon_by_code($candidate);
    if ($exists === null) {
        $couponCode = $candidate;
        break;
    }
}

if ($couponCode === null) {
    api_error('COUPON_CODE_GENERATION_FAILED', 'クーポンコードの生成に失敗しました。', 500);
}

/**
 * coupons 挿入データ
 */
$insertValues = [];

if (in_array('coupon_code', $couponColumns, true)) {
    $insertValues['coupon_code'] = $couponCode;
}
if (in_array('coupon_plan_id', $couponColumns, true)) {
    $insertValues['coupon_plan_id'] = (string)($plan['id'] ?? '');
}
if (in_array('issued_at', $couponColumns, true)) {
    $insertValues['issued_at'] = $issuedAt;
}
if (in_array('issued_discount_rate', $couponColumns, true)) {
    $insertValues['issued_discount_rate'] = $issuedDiscountRate;
}
if (in_array('used_at', $couponColumns, true)) {
    $insertValues['used_at'] = null;
}
if (in_array('used_discount_rate', $couponColumns, true)) {
    $insertValues['used_discount_rate'] = null;
}
if (in_array('user_id', $couponColumns, true)) {
    $insertValues['user_id'] = $userId;
}
if (in_array('notes', $couponColumns, true)) {
    $insertValues['notes'] = $notes;
}
if (in_array('created_at', $couponColumns, true)) {
    $insertValues['created_at'] = $issuedAt;
}
if (in_array('updated_at', $couponColumns, true)) {
    $insertValues['updated_at'] = $issuedAt;
}

if ($insertValues === []) {
    api_error('COUPON_INSERT_VALUES_EMPTY', 'クーポン作成用の挿入データがありません。', 500);
}

$insertColumns = array_keys($insertValues);
$placeholders = array_map(
    static fn(string $column): string => ':' . $column,
    $insertColumns
);

$sql = sprintf(
    'INSERT INTO coupons (%s) VALUES (%s)',
    implode(', ', $insertColumns),
    implode(', ', $placeholders)
);

$params = [];
foreach ($insertValues as $column => $value) {
    $params[':' . $column] = $value;
}

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
} catch (Throwable $e) {
    api_error('COUPON_ISSUE_FAILED', 'クーポン発行に失敗しました。', 500, [
        'debug' => [
            'message' => $e->getMessage(),
        ],
    ]);
}

/**
 * 挿入後の取得
 * id 自動採番か文字列か不明なため、coupon_code で再取得する
 */
$createdCoupon = find_coupon_by_code($couponCode);
if ($createdCoupon === null) {
    api_error('COUPON_CREATED_BUT_NOT_FETCHED', 'クーポンは作成されましたが再取得に失敗しました。', 500);
}

$viewModel = build_plan_view_model($plan);
$schedule = $viewModel['schedule'] ?? [];

api_success([
    'message' => 'クーポンを発行しました。',
    'coupon' => [
        'id' => (string)($createdCoupon['id'] ?? ''),
        'coupon_code' => (string)($createdCoupon['coupon_code'] ?? ''),
        'coupon_plan_id' => (string)($createdCoupon['coupon_plan_id'] ?? ''),
        'issued_at' => (string)($createdCoupon['issued_at'] ?? $issuedAt),
        'issued_discount_rate' => (float)($createdCoupon['issued_discount_rate'] ?? $issuedDiscountRate),
        'used_at' => (string)($createdCoupon['used_at'] ?? ''),
        'used_discount_rate' => (float)($createdCoupon['used_discount_rate'] ?? 0),
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
], 201);