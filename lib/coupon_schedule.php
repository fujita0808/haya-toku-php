<?php

declare(strict_types=1);

if (!function_exists('schedule_total_days')) {
    /**
     * 早得期間の総日数を返す
     * 開始日と終了日を「日単位」で見て、両端含みで数える
     */
    function schedule_total_days(array $plan): int
    {
        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        if ($startTs === false || $endTs === false) {
            return 1;
        }

        $startDate = strtotime(date('Y-m-d 00:00:00', $startTs));
        $endDate = strtotime(date('Y-m-d 00:00:00', $endTs));

        $days = (int)floor(($endDate - $startDate) / 86400) + 1;

        return max(1, $days);
    }
}

if (!function_exists('schedule_elapsed_days')) {
    /**
     * 現在日時が、開始日から見て何日目かを返す
     * 開始日当日は 0、翌日は 1
     */
    function schedule_elapsed_days(array $plan, ?int $nowTs = null): int
    {
        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        if ($startTs === false) {
            return 0;
        }

        $nowTs ??= time();

        $startDate = strtotime(date('Y-m-d 00:00:00', $startTs));
        $nowDate = strtotime(date('Y-m-d 00:00:00', $nowTs));

        $elapsed = (int)floor(($nowDate - $startDate) / 86400);

        return max(0, $elapsed);
    }
}

if (!function_exists('schedule_remaining_days')) {
    /**
     * 終了日までの残日数を返す
     * 当日を含めた概念で返す
     */
    function schedule_remaining_days(array $plan, ?int $nowTs = null): int
    {
        $endTs = strtotime((string)($plan['end_at'] ?? ''));
        if ($endTs === false) {
            return 0;
        }

        $nowTs ??= time();

        $endDate = strtotime(date('Y-m-d 00:00:00', $endTs));
        $nowDate = strtotime(date('Y-m-d 00:00:00', $nowTs));

        $remaining = (int)floor(($endDate - $nowDate) / 86400) + 1;

        return max(0, $remaining);
    }
}

if (!function_exists('schedule_progress_ratio')) {
    /**
     * 期間進捗率を 0.0 ～ 1.0 で返す
     */
    function schedule_progress_ratio(array $plan, ?int $nowTs = null): float
    {
        $totalDays = schedule_total_days($plan);
        if ($totalDays <= 1) {
            return 1.0;
        }

        $elapsedDays = schedule_elapsed_days($plan, $nowTs);
        $ratio = $elapsedDays / ($totalDays - 1);

        if ($ratio < 0) {
            return 0.0;
        }
        if ($ratio > 1) {
            return 1.0;
        }

        return $ratio;
    }
}

if (!function_exists('schedule_current_discount_rate')) {
    /**
     * 現在割引率を返す
     * 早得仕様：
     * - 開始日 = initial_discount_rate
     * - 終了日 = min_discount_rate
     * - 日次で線形に変動
     * - 未開始は initial
     * - 終了後は min
     */
    function schedule_current_discount_rate(array $plan, ?int $nowTs = null): float
    {
        $nowTs ??= time();

        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        $initial = isset($plan['initial_discount_rate'])
            ? (float)$plan['initial_discount_rate']
            : 0.0;

        $min = isset($plan['min_discount_rate'])
            ? (float)$plan['min_discount_rate']
            : 0.0;

        if ($initial < $min) {
            $tmp = $initial;
            $initial = $min;
            $min = $tmp;
        }

        if ($startTs === false || $endTs === false) {
            return round($initial, 4);
        }

        if ($nowTs <= $startTs) {
            return round($initial, 4);
        }

        if ($nowTs >= $endTs) {
            return round($min, 4);
        }

        $ratio = schedule_progress_ratio($plan, $nowTs);
        $rate = $initial - (($initial - $min) * $ratio);

        if ($rate < $min) {
            $rate = $min;
        }
        if ($rate > $initial) {
            $rate = $initial;
        }

        return round($rate, 4);
    }
}

if (!function_exists('schedule_next_change_at')) {
    /**
     * 次回割引率が変わる日時を返す
     * 日次変動なので翌日 00:00:00 を返す
     * 変化しない場合は null
     */
    function schedule_next_change_at(array $plan, ?int $nowTs = null): ?string
    {
        $nowTs ??= time();

        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        if ($startTs === false || $endTs === false) {
            return null;
        }

        if ($nowTs < $startTs) {
            return date('c', $startTs);
        }

        if ($nowTs >= $endTs) {
            return null;
        }

        $nextDayTs = strtotime(date('Y-m-d 00:00:00', $nowTs) . ' +1 day');

        if ($nextDayTs === false) {
            return null;
        }

        if ($nextDayTs > $endTs) {
            return null;
        }

        return date('c', $nextDayTs);
    }
}

if (!function_exists('schedule_discount_timeline')) {
    /**
     * 日次タイムラインを返す
     *
     * @return array<int, array<string, mixed>>
     */
    function schedule_discount_timeline(array $plan): array
    {
        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        if ($startTs === false || $endTs === false) {
            return [];
        }

        $timeline = [];
        $currentDayTs = strtotime(date('Y-m-d 00:00:00', $startTs));
        $lastDayTs = strtotime(date('Y-m-d 00:00:00', $endTs));

        if ($currentDayTs === false || $lastDayTs === false) {
            return [];
        }

        while ($currentDayTs <= $lastDayTs) {
            $timeline[] = [
                'date' => date('Y-m-d', $currentDayTs),
                'rate' => schedule_current_discount_rate($plan, $currentDayTs),
            ];

            $nextTs = strtotime('+1 day', $currentDayTs);
            if ($nextTs === false) {
                break;
            }
            $currentDayTs = $nextTs;
        }

        return $timeline;
    }
}

if (!function_exists('schedule_is_active_now')) {
    /**
     * 現在、公開期間内かどうか
     */
    function schedule_is_active_now(array $plan, ?int $nowTs = null): bool
    {
        $nowTs ??= time();

        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        if ($startTs === false || $endTs === false) {
            return false;
        }

        return $nowTs >= $startTs && $nowTs <= $endTs;
    }
}

if (!function_exists('schedule_status_label')) {
    /**
     * 画面表示用の状態ラベル
     */
    function schedule_status_label(array $plan, ?int $nowTs = null): string
    {
        $nowTs ??= time();

        $startTs = strtotime((string)($plan['start_at'] ?? ''));
        $endTs = strtotime((string)($plan['end_at'] ?? ''));

        if ($startTs === false || $endTs === false) {
            return '設定不正';
        }

        if ($nowTs < $startTs) {
            return '公開前';
        }

        if ($nowTs > $endTs) {
            return '終了';
        }

        return '公開中';
    }
}

if (!function_exists('schedule_snapshot')) {
    /**
     * APIや画面で使いやすい現在値一式を返す
     *
     * @return array<string, mixed>
     */
    function schedule_snapshot(array $plan, ?int $nowTs = null): array
    {
        $nowTs ??= time();

        return [
            'status' => schedule_status_label($plan, $nowTs),
            'is_active_now' => schedule_is_active_now($plan, $nowTs),
            'current_discount_rate' => schedule_current_discount_rate($plan, $nowTs),
            'initial_discount_rate' => isset($plan['initial_discount_rate'])
                ? round((float)$plan['initial_discount_rate'], 4)
                : 0.0,
            'min_discount_rate' => isset($plan['min_discount_rate'])
                ? round((float)$plan['min_discount_rate'], 4)
                : 0.0,
            'total_days' => schedule_total_days($plan),
            'elapsed_days' => schedule_elapsed_days($plan, $nowTs),
            'remaining_days' => schedule_remaining_days($plan, $nowTs),
            'progress_ratio' => round(schedule_progress_ratio($plan, $nowTs), 4),
            'next_change_at' => schedule_next_change_at($plan, $nowTs),
        ];
    }
}

if (!function_exists('schedule_confirm_used_discount_rate')) {
    /**
     * 利用時点の割引率を確定する
     * coupon_use.php から呼ぶ想定
     */
    function schedule_confirm_used_discount_rate(array $plan, ?int $usedAtTs = null): float
    {
        return schedule_current_discount_rate($plan, $usedAtTs);
    }
}