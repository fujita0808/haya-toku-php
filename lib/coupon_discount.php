<?php

declare(strict_types=1);

function calculateCurrentDiscountRate(array $plan, string $issuedAt, ?DateTimeImmutable $now = null): float
{
    $initial = isset($plan['initial_discount_rate']) ? (float)$plan['initial_discount_rate'] : 0.0;
    $min = isset($plan['min_discount_rate']) ? (float)$plan['min_discount_rate'] : 0.0;
    $mode = $plan['discount_mode'] ?? null;
    $type = $plan['decay_type'] ?? null;
    $intervalMinutes = isset($plan['decay_interval_minutes']) ? (int)$plan['decay_interval_minutes'] : 0;
    $stepRate = isset($plan['decay_step_rate']) ? (float)$plan['decay_step_rate'] : 0.0;

    if ($now === null) {
        $now = new DateTimeImmutable('now');
    }

    $issuedAtDt = new DateTimeImmutable($issuedAt);

    if ($mode !== 'step' || $type !== 'step' || $intervalMinutes <= 0 || $stepRate <= 0) {
        return round(max($min, $initial), 4);
    }

    $elapsedSeconds = $now->getTimestamp() - $issuedAtDt->getTimestamp();
    if ($elapsedSeconds < 0) {
        $elapsedSeconds = 0;
    }

    $stepsElapsed = intdiv($elapsedSeconds, $intervalMinutes * 60);

    $current = $initial - ($stepsElapsed * $stepRate);

    if ($current < $min) {
        $current = $min;
    }

    return round($current, 4);
}
