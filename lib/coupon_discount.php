<?php

declare(strict_types=1);

/**
 * このファイルは旧割引ロジックの橋渡し層です。
 *
 * 方針：
 * - 実際の割引計算本体は coupon_schedule.php に集約する
 * - ここでは旧呼び出しとの互換だけを担う
 * - 将来的に参照がなくなったら削除対象
 */

if (!function_exists('calculate_discount_rate')) {
    /**
     * 現在割引率を返す
     *
     * 旧コード互換用。
     * 実体は schedule_current_discount_rate() に委譲する。
     */
    function calculate_discount_rate(array $plan, $now = null): float
    {
        $nowTs = normalize_discount_time_to_timestamp($now);

        return schedule_current_discount_rate($plan, $nowTs);
    }
}

if (!function_exists('generate_discount_timeline')) {
    /**
     * 日次割引タイムラインを返す
     *
     * 旧コード互換用。
     * 実体は schedule_discount_timeline() に委譲する。
     *
     * @return array<int, array<string, mixed>>
     */
    function generate_discount_timeline(array $plan): array
    {
        return schedule_discount_timeline($plan);
    }
}

if (!function_exists('calculateCurrentDiscountRate')) {
    /**
     * 旧キャメルケース関数の互換
     *
     * 以前は issuedAt を考慮していた可能性があるが、
     * 現仕様では「未使用中は流動、利用時点で確定」なので
     * issuedAt に固定されない。
     */
    function calculateCurrentDiscountRate(array $plan, $issuedAt = null, $now = null): float
    {
        $nowTs = normalize_discount_time_to_timestamp($now);

        return schedule_current_discount_rate($plan, $nowTs);
    }
}

if (!function_exists('build_plan_discount_timeline')) {
    /**
     * 旧名称の互換
     *
     * @return array<int, array<string, mixed>>
     */
    function build_plan_discount_timeline(array $plan): array
    {
        return schedule_discount_timeline($plan);
    }
}

if (!function_exists('get_plan_discount_snapshot')) {
    /**
     * 現在値スナップショットを返す
     *
     * @return array<string, mixed>
     */
    function get_plan_discount_snapshot(array $plan, $now = null): array
    {
        $nowTs = normalize_discount_time_to_timestamp($now);

        return schedule_snapshot($plan, $nowTs);
    }
}

if (!function_exists('confirm_used_discount_rate')) {
    /**
     * 利用時点の割引率確定
     */
    function confirm_used_discount_rate(array $plan, $usedAt = null): float
    {
        $usedAtTs = normalize_discount_time_to_timestamp($usedAt);

        return schedule_confirm_used_discount_rate($plan, $usedAtTs);
    }
}

if (!function_exists('normalize_discount_time_to_timestamp')) {
    /**
     * 旧関数群から渡される時刻引数を timestamp に正規化する
     *
     * 対応:
     * - null
     * - int timestamp
     * - DateTimeInterface
     * - 文字列日時
     */
    function normalize_discount_time_to_timestamp($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_string($value)) {
            $ts = strtotime($value);
            return $ts === false ? null : $ts;
        }

        return null;
    }
}