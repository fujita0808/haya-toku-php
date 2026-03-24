<?php

declare(strict_types=1);

/**
 * 文字列/数値を 0.0〜1.0 の割引率に正規化する
 * 例:
 *   0.2  -> 0.2
 *   20   -> 0.2
 *   null -> 0.0
 */
function normalize_discount_rate(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    $rate = (float)$value;

    if ($rate > 1.0) {
        $rate = $rate / 100.0;
    }

    if ($rate < 0.0) {
        return 0.0;
    }

    if ($rate > 1.0) {
        return 1.0;
    }

    return $rate;
}

/**
 * 日付文字列を DateTimeImmutable にする
 */
function to_tokyo_datetime(string $datetime): DateTimeImmutable
{
    return new DateTimeImmutable($datetime, new DateTimeZone('Asia/Tokyo'));
}

/**
 * 公開開始日〜公開終了日の「日数差」を返す
 * 例:
 *   3/24 00:00 ~ 3/31 23:59 の場合、日付差は 7
 *
 * 線形減衰の分母として 0 を避けるため、最小 1 を返す
 */
function calculate_public_period_days(string $startAt, string $endAt): int
{
    $start = to_tokyo_datetime($startAt)->setTime(0, 0, 0);
    $end = to_tokyo_datetime($endAt)->setTime(0, 0, 0);

    $days = (int)$start->diff($end)->days;

    return max(1, $days);
}

/**
 * 指定日の発行時点での経過日数を返す
 * 発行日当日は 0
 */
function calculate_elapsed_days_from_plan_start(
    string $planStartAt,
    ?string $issueAt = null
): int {
    $issueAt = $issueAt ?: date('Y-m-d H:i:s');

    $start = to_tokyo_datetime($planStartAt)->setTime(0, 0, 0);
    $issue = to_tokyo_datetime($issueAt)->setTime(0, 0, 0);

    $days = (int)$start->diff($issue)->days;

    if ($issue < $start) {
        return 0;
    }

    return max(0, $days);
}

/**
 * 日次減衰率を計算する
 */
function calculate_daily_decay_rate(array $plan): float
{
    $initial = normalize_discount_rate($plan['initial_discount_rate'] ?? 0);
    $min = normalize_discount_rate($plan['min_discount_rate'] ?? 0);

    if ($min > $initial) {
        $min = $initial;
    }

    $startAt = (string)($plan['start_at'] ?? '');
    $endAt = (string)($plan['end_at'] ?? '');

    if ($startAt === '' || $endAt === '') {
        return 0.0;
    }

    $periodDays = calculate_public_period_days($startAt, $endAt);
    $diff = $initial - $min;

    if ($diff <= 0) {
        return 0.0;
    }

    return $diff / $periodDays;
}

/**
 * 発行時点の割引率を計算する
 * 0324仕様:
 * - 減衰基準は公開期間
 * - 発行日時点の日付で計算
 * - 発行後は固定
 * - 線形減衰
 */
function calculate_issue_discount_rate(array $plan, ?string $issueAt = null): float
{
    $initial = normalize_discount_rate($plan['initial_discount_rate'] ?? 0);
    $min = normalize_discount_rate($plan['min_discount_rate'] ?? 0);

    if ($min > $initial) {
        $min = $initial;
    }

    $startAt = (string)($plan['start_at'] ?? '');
    $endAt = (string)($plan['end_at'] ?? '');

    if ($startAt === '' || $endAt === '') {
        return $initial;
    }

    $elapsedDays = calculate_elapsed_days_from_plan_start($startAt, $issueAt);
    $dailyDecayRate = calculate_daily_decay_rate($plan);

    $rate = $initial - ($elapsedDays * $dailyDecayRate);

    if ($rate < $min) {
        $rate = $min;
    }

    if ($rate > $initial) {
        $rate = $initial;
    }

    return round($rate, 4);
}

/**
 * 旧コード互換用
 * 今後は発行時のみ使う
 */
function calculate_discount_rate(array $plan, ?string $issueAt = null): float
{
    return calculate_issue_discount_rate($plan, $issueAt);
}

/**
 * 発行日からの経過日数
 * 参照用。割引率の再計算には使わない
 */
function calculateElapsedDaysByDate(string $issuedAt, ?string $now = null): int
{
    $issued = to_tokyo_datetime($issuedAt)->setTime(0, 0, 0);
    $current = $now
        ? to_tokyo_datetime($now)->setTime(0, 0, 0)
        : (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->setTime(0, 0, 0);

    if ($current < $issued) {
        return 0;
    }

    return (int)$issued->diff($current)->days;
}