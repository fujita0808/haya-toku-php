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

$couponId = trim((string)($input['coupon_id'] ?? ''));
$couponCode = trim((string)($input['coupon_code'] ?? ''));
$staffId = trim((string)($input['staff_id'] ?? ''));
$userId = trim((string)($input['user_id'] ?? ''));
$notes = trim((string)($input['notes'] ?? ''));

if ($couponId === '' && $couponCode === '') {
    api_error('COUPON_IDENTIFIER_REQUIRED', 'coupon_id または coupon_code が必要です。', 400);
}

if (!function_exists('find_coupon_by_id') || !function_exists('find_coupon_by_code')) {
    api_error(
        'COUPON_LOOKUP_NOT_IMPLEMENTED',
        'クーポン検索処理が未実装です。coupon 取得関数を追加してください。',
        500
    );
}

$coupon = null;

if ($couponId !== '') {
    $coupon = find_coupon_by_id($couponId);
} elseif ($couponCode !== '') {
    $coupon = find_coupon_by_code($couponCode);
}

if (!is_array($coupon)) {
    api_error('COUPON_NOT_FOUND', '対象クーポンが見つかりません。', 404);
}

$planId = trim((string)($coupon['coupon_plan_id'] ?? $coupon['plan_id'] ?? ''));
$plan = $planId !== '' ? find_plan_by_id($planId) : null;

$denyReason = coupon_use_denied_reason($coupon, $plan);
if ($denyReason !== 'ok') {
    api_error(strtoupper($denyReason), coupon_use_denied_message($denyReason), 400, [
        'coupon' => [
            'id' => (string)($coupon['id'] ?? ''),
            'coupon_code' => (string)($coupon['coupon_code'] ?? ''),
        ],
    ]);
}

/**
 * 利用時点の割引率を確定
 */
$usedAtTs = time();
$usedAt = date('Y-m-d H:i:s', $usedAtTs);
$usedDiscountRate = schedule_confirm_used_discount_rate($plan, $usedAtTs);

$pdo = db();

try {
    $pdo->beginTransaction();

    /**
     * coupons 更新
     * used_at / used_discount_rate を保存
     */
    $couponColumns = [];
    $stmtColumns = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'coupons'
    ");
    foreach ($stmtColumns->fetchAll() as $row) {
        $couponColumns[] = (string)$row['column_name'];
    }

    $setParts = [];
    $params = [
        ':id' => (string)$coupon['id'],
    ];

    if (in_array('used_at', $couponColumns, true)) {
        $setParts[] = 'used_at = :used_at';
        $params[':used_at'] = $usedAt;
    }

    if (in_array('used_discount_rate', $couponColumns, true)) {
        $setParts[] = 'used_discount_rate = :used_discount_rate';
        $params[':used_discount_rate'] = $usedDiscountRate;
    }

    if (in_array('updated_at', $couponColumns, true)) {
        $setParts[] = 'updated_at = :updated_at';
        $params[':updated_at'] = $usedAt;
    }

    if ($setParts === []) {
        throw new RuntimeException('coupons テーブルに更新対象カラムがありません。');
    }

    $updateCouponSql = sprintf(
        'UPDATE coupons SET %s WHERE id = :id',
        implode(', ', $setParts)
    );

    $stmtUpdateCoupon = $pdo->prepare($updateCouponSql);
    $stmtUpdateCoupon->execute($params);

    /**
     * usage_logs 追記
     * テーブルがあれば保存する
     */
    $usageLogColumns = [];
    $stmtUsageLogColumns = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'usage_logs'
    ");
    foreach ($stmtUsageLogColumns->fetchAll() as $row) {
        $usageLogColumns[] = (string)$row['column_name'];
    }

    if ($usageLogColumns !== []) {
        $usageLogValues = [];

        if (in_array('coupon_id', $usageLogColumns, true)) {
            $usageLogValues['coupon_id'] = (string)($coupon['id'] ?? '');
        }
        if (in_array('coupon_code', $usageLogColumns, true)) {
            $usageLogValues['coupon_code'] = (string)($coupon['coupon_code'] ?? '');
        }
        if (in_array('coupon_plan_id', $usageLogColumns, true)) {
            $usageLogValues['coupon_plan_id'] = $planId;
        }
        if (in_array('used_at', $usageLogColumns, true)) {
            $usageLogValues['used_at'] = $usedAt;
        }
        if (in_array('used_discount_rate', $usageLogColumns, true)) {
            $usageLogValues['used_discount_rate'] = $usedDiscountRate;
        }
        if (in_array('staff_id', $usageLogColumns, true)) {
            $usageLogValues['staff_id'] = $staffId;
        }
        if (in_array('user_id', $usageLogColumns, true)) {
            $usageLogValues['user_id'] = $userId;
        }
        if (in_array('notes', $usageLogColumns, true)) {
            $usageLogValues['notes'] = $notes;
        }
        if (in_array('created_at', $usageLogColumns, true)) {
            $usageLogValues['created_at'] = $usedAt;
        }
        if (in_array('updated_at', $usageLogColumns, true)) {
            $usageLogValues['updated_at'] = $usedAt;
        }

        if ($usageLogValues !== []) {
            $insertColumns = array_keys($usageLogValues);
            $insertPlaceholders = array_map(
                static fn(string $column): string => ':' . $column,
                $insertColumns
            );

            $insertUsageSql = sprintf(
                'INSERT INTO usage_logs (%s) VALUES (%s)',
                implode(', ', $insertColumns),
                implode(', ', $insertPlaceholders)
            );

            $insertParams = [];
            foreach ($usageLogValues as $column => $value) {
                $insertParams[':' . $column] = $value;
            }

            $stmtInsertUsage = $pdo->prepare($insertUsageSql);
            $stmtInsertUsage->execute($insertParams);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    api_error('COUPON_USE_FAILED', 'クーポン利用確定に失敗しました。', 500, [
        'debug' => [
            'message' => $e->getMessage(),
        ],
    ]);
}

$updatedCoupon = null;
if ($couponId !== '' && function_exists('find_coupon_by_id')) {
    $updatedCoupon = find_coupon_by_id((string)$coupon['id']);
} elseif ($couponCode !== '' && function_exists('find_coupon_by_code')) {
    $updatedCoupon = find_coupon_by_code((string)$coupon['coupon_code']);
}

api_success([
    'message' => 'クーポンを使用済みにしました。',
    'coupon' => [
        'id' => (string)($updatedCoupon['id'] ?? $coupon['id'] ?? ''),
        'coupon_code' => (string)($updatedCoupon['coupon_code'] ?? $coupon['coupon_code'] ?? ''),
        'coupon_plan_id' => $planId,
        'used_at' => (string)($updatedCoupon['used_at'] ?? $usedAt),
        'used_discount_rate' => (float)($updatedCoupon['used_discount_rate'] ?? $usedDiscountRate),
    ],
    'plan' => [
        'id' => (string)($plan['id'] ?? ''),
        'title' => (string)($plan['title'] ?? ''),
        'product_name' => (string)($plan['product_name'] ?? ''),
    ],
], 200);