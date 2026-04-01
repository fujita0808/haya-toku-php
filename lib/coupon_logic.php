<?php

declare(strict_types=1);

/**
 * coupon_logic.php
 *
 * 役割：
 * - 発行可否
 * - 利用可否
 * - 使用済み判定
 * - 公開前 / 公開中 / 終了判定
 * - フロント表示対象として選択可能か判定
 * - 現在フロントに表示すべきプラン解決
 * - 画面/API用の状態メッセージ生成
 *
 * 方針：
 * - 割引率の数値計算は coupon_schedule.php に任せる
 * - DB保存/取得は db.php に任せる
 * - ここでは業務上の判断だけを扱う
 */

if (!function_exists('plan_is_enabled')) {
    function plan_is_enabled(array $plan): bool
    {
        return !empty($plan['is_active']);
    }
}

if (!function_exists('plan_is_started')) {
    function plan_is_started(array $plan, ?int $nowTs = null): bool
    {
        $nowTs ??= time();

        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        if ($startTs === false) {
            return false;
        }

        return $nowTs >= $startTs;
    }
}

if (!function_exists('plan_is_ended')) {
    function plan_is_ended(array $plan, ?int $nowTs = null): bool
    {
        $nowTs ??= time();

        $endTs = strtotime((string)($plan['end_at'] ?? ''));
        if ($endTs === false) {
            return false;
        }

        return $nowTs > $endTs;
    }
}

if (!function_exists('plan_is_available_now')) {
    function plan_is_available_now(array $plan, ?int $nowTs = null): bool
    {
        return plan_is_enabled($plan)
            && plan_is_started($plan, $nowTs)
            && !plan_is_ended($plan, $nowTs);
    }
}

if (!function_exists('plan_status_code')) {
    /**
     * plan の業務状態コードを返す
     *
     * 戻り値例:
     * - draft
     * - scheduled
     * - active
     * - ended
     * - invalid
     */
    function plan_status_code(array $plan, ?int $nowTs = null): string
    {
        $nowTs ??= time();

        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        if ($startTs === false || $endTs === false) {
            return 'invalid';
        }

        if ($startTs > $endTs) {
            return 'invalid';
        }

        if (!plan_is_enabled($plan)) {
            return 'draft';
        }

        if ($nowTs < $startTs) {
            return 'scheduled';
        }

        if ($nowTs > $endTs) {
            return 'ended';
        }

        return 'active';
    }
}

if (!function_exists('plan_status_label')) {
    /**
     * plan の日本語状態ラベルを返す
     */
    function plan_status_label(array $plan, ?int $nowTs = null): string
    {
        return match (plan_status_code($plan, $nowTs)) {
            'draft' => '下書き',
            'scheduled' => '公開前',
            'active' => '公開中',
            'ended' => '終了',
            default => '設定不正',
        };
    }
}

if (!function_exists('plan_can_be_selected_for_front')) {
    /**
     * フロント表示対象として選択可能か
     * 現在は「公開中(active)」のときのみ選択可能
     */
    function plan_can_be_selected_for_front(array $plan, ?int $nowTs = null): bool
    {
        return plan_status_code($plan, $nowTs) === 'active';
    }
}

if (!function_exists('plan_can_be_displayed_now')) {
    /**
     * plan が「今フロント表示可能」か判定する
     * 現在は「公開中(active)」のときのみ表示可能
     */
    function plan_can_be_displayed_now(array $plan, ?int $nowTs = null): bool
    {
        return plan_status_code($plan, $nowTs) === 'active';
    }
}

if (!function_exists('resolve_current_display_plan')) {
    /**
     * dashboard で保存した表示対象を優先して取得する
     * ただし、現在表示可能である場合のみ採用する
     *
     * 前提:
     * - get_display_target_plan_id()
     * - find_plan_by_id()
     * - find_current_plan()
     * が bootstrap 経由で利用可能
     */
    function resolve_current_display_plan(): ?array
    {
        if (function_exists('get_display_target_plan_id')) {
            $displayPlanId = get_display_target_plan_id();

            if (is_string($displayPlanId) && trim($displayPlanId) !== '') {
                $selectedPlan = find_plan_by_id(trim($displayPlanId));

                if (is_array($selectedPlan) && plan_can_be_displayed_now($selectedPlan)) {
                    return $selectedPlan;
                }
            }
        }

        return find_current_plan();
    }
}

if (!function_exists('coupon_is_used')) {
    function coupon_is_used(array $coupon): bool
    {
        $usedAt = $coupon['used_at'] ?? null;

        return is_string($usedAt)
            ? trim($usedAt) !== ''
            : !empty($usedAt);
    }
}

if (!function_exists('coupon_has_plan_id')) {
    function coupon_has_plan_id(array $coupon): bool
    {
        $planId = $coupon['coupon_plan_id'] ?? $coupon['plan_id'] ?? '';

        return is_string($planId)
            ? trim($planId) !== ''
            : !empty($planId);
    }
}

if (!function_exists('coupon_is_usable')) {
    /**
     * クーポンが利用可能か判定する
     *
     * @param array<string, mixed> $coupon
     * @param array<string, mixed>|null $plan
     */
    function coupon_is_usable(array $coupon, ?array $plan, ?int $nowTs = null): bool
    {
        if ($plan === null) {
            return false;
        }

        if (!coupon_has_plan_id($coupon)) {
            return false;
        }

        if (coupon_is_used($coupon)) {
            return false;
        }

        if (!plan_is_available_now($plan, $nowTs)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('coupon_use_denied_reason')) {
    /**
     * 利用不可理由コードを返す
     *
     * 戻り値例:
     * - plan_not_found
     * - coupon_used
     * - plan_draft
     * - plan_scheduled
     * - plan_ended
     * - invalid
     * - ok
     *
     * @param array<string, mixed> $coupon
     * @param array<string, mixed>|null $plan
     */
    function coupon_use_denied_reason(array $coupon, ?array $plan, ?int $nowTs = null): string
    {
        if ($plan === null) {
            return 'plan_not_found';
        }

        if (!coupon_has_plan_id($coupon)) {
            return 'invalid';
        }

        if (coupon_is_used($coupon)) {
            return 'coupon_used';
        }

        return match (plan_status_code($plan, $nowTs)) {
            'draft' => 'plan_draft',
            'scheduled' => 'plan_scheduled',
            'ended' => 'plan_ended',
            'invalid' => 'invalid',
            default => 'ok',
        };
    }
}

if (!function_exists('coupon_use_denied_message')) {
    /**
     * 利用不可理由の日本語メッセージ
     */
    function coupon_use_denied_message(string $reason): string
    {
        return match ($reason) {
            'plan_not_found' => '対象プランが見つかりません。',
            'coupon_used' => 'このクーポンはすでに使用済みです。',
            'plan_draft' => 'このクーポンはまだ公開されていません。',
            'plan_scheduled' => 'このクーポンは公開前です。',
            'plan_ended' => 'このクーポンは利用期間が終了しています。',
            'invalid' => 'クーポン状態が不正です。',
            default => '利用可能です。',
        };
    }
}

if (!function_exists('build_plan_view_model')) {
    /**
     * 画面/API向けに、状態と schedule 情報をまとめた view model を返す
     *
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    function build_plan_view_model(array $plan, ?int $nowTs = null): array
    {
        $nowTs ??= time();

        $schedule = schedule_snapshot($plan, $nowTs);

        return [
            'id' => (string)($plan['id'] ?? ''),
            'title' => (string)($plan['title'] ?? ''),
            'description' => (string)($plan['description'] ?? ''),
            'product_name' => (string)($plan['product_name'] ?? ''),
            'is_active' => !empty($plan['is_active']),
            'status_code' => plan_status_code($plan, $nowTs),
            'status_label' => plan_status_label($plan, $nowTs),
            'start_at' => (string)($plan['start_at'] ?? ''),
            'end_at' => (string)($plan['end_at'] ?? ''),
            'initial_discount_rate' => (float)($plan['initial_discount_rate'] ?? 0),
            'min_discount_rate' => (float)($plan['min_discount_rate'] ?? 0),
            'schedule' => $schedule,
            'rules' => is_array($plan['rules'] ?? null) ? $plan['rules'] : [],
            'notes' => (string)($plan['notes'] ?? ''),
        ];
    }
}