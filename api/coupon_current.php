<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

/**
 * プランが「今表示可能」か判定
 * - is_active = true
 * - start_at <= now
 * - end_at >= now
 */
function can_display_now(array $plan, ?int $nowTs = null): bool
{
    $nowTs ??= time();

    if (empty($plan['is_active'])) {
        return false;
    }

    $startTs = strtotime((string)($plan['start_at'] ?? ''));
    $endTs   = strtotime((string)($plan['end_at'] ?? ''));

    if ($startTs === false || $endTs === false) {
        return false;
    }

    return $startTs <= $nowTs && $endTs >= $nowTs;
}

/**
 * dashboard で保存した表示対象を優先して取得
 * ただし、現在表示可能である場合のみ採用する
 */
function resolve_current_display_plan(): ?array
{
    if (function_exists('get_display_target_plan_id')) {
        $displayPlanId = get_display_target_plan_id();

        if (is_string($displayPlanId) && trim($displayPlanId) !== '') {
            $selectedPlan = find_plan_by_id(trim($displayPlanId));

            if (is_array($selectedPlan) && can_display_now($selectedPlan)) {
                return $selectedPlan;
            }
        }
    }

    return find_current_plan();
}

$plan = resolve_current_display_plan();

if (!$plan) {
    $payload = [
        'ok' => false,
        'app' => [
            'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
            'displayName' => HAYA_TOKU_APP_NAME,
        ],
        'error' => [
            'code' => 'PLAN_NOT_FOUND',
            'message' => '現在表示可能なクーポンがありません。',
        ],
    ];

    if (function_exists('api_error')) {
        api_error('PLAN_NOT_FOUND', '現在表示可能なクーポンがありません。', 404, [
            'app' => $payload['app'],
        ]);
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
        'documentTitle' => '早得クーポン | HAYA-TOKU（🍊ver / PHP PoC）',
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