<?php

namespace App\Domain\Finance;

final class FinancialMetrics
{
    public function percentage(float|int $value, float|int $base): float
    {
        $base = (float) $base;

        return $base <= 0.0 ? 0.0 : ((float) $value / $base) * 100;
    }

    public function progress(float|int $value, float|int $target): float
    {
        return min(100.0, max(0.0, $this->percentage($value, $target)));
    }

    public function perUnit(float|int $value, float|int $units): float
    {
        $units = (float) $units;

        return $units > 0.0 ? (float) $value / $units : 0.0;
    }

    /**
     * @return array{planned: float, actual: float, variance: float, percentage: float, progress: float, variance_tone: string}
     */
    public function budgetPerformance(float|int $planned, float|int $actual): array
    {
        $planned = (float) $planned;
        $actual = (float) $actual;
        $variance = $actual - $planned;

        return [
            'planned' => $planned,
            'actual' => $actual,
            'variance' => $variance,
            'percentage' => $planned > 0.0 ? $this->percentage($actual, $planned) : 0.0,
            'progress' => $planned > 0.0 ? $this->progress($actual, $planned) : 0.0,
            'variance_tone' => $variance > 0.0 ? 'negative' : 'positive',
        ];
    }

    /**
     * @return array{value_per_hectare: float, sacks_per_hectare: float, share_percentage: float}
     */
    public function categoryDistribution(
        float|int $value,
        float|int $total,
        float|int $area,
        float|int $averageSackPrice,
    ): array {
        $valuePerHectare = $this->perUnit($value, $area);

        return [
            'value_per_hectare' => $valuePerHectare,
            'sacks_per_hectare' => $this->perUnit($valuePerHectare, $averageSackPrice),
            'share_percentage' => $this->percentage($value, $total),
        ];
    }
}
