<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\FinancialMetrics;
use PHPUnit\Framework\TestCase;

class FinancialMetricsTest extends TestCase
{
    private FinancialMetrics $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = new FinancialMetrics;
    }

    public function test_percentage_is_zero_when_base_is_not_positive(): void
    {
        $this->assertSame(0.0, $this->metrics->percentage(100, 0));
        $this->assertSame(0.0, $this->metrics->percentage(100, -10));
    }

    public function test_progress_is_limited_to_the_zero_to_one_hundred_range(): void
    {
        $this->assertSame(100.0, $this->metrics->progress(150, 100));
        $this->assertSame(0.0, $this->metrics->progress(-10, 100));
    }

    public function test_budget_performance_exposes_variance_percentage_and_tone(): void
    {
        $performance = $this->metrics->budgetPerformance(1_000, 1_250);

        $this->assertSame(250.0, $performance['variance']);
        $this->assertSame(125.0, $performance['percentage']);
        $this->assertSame(100.0, $performance['progress']);
        $this->assertSame('negative', $performance['variance_tone']);
    }

    public function test_category_distribution_calculates_per_hectare_sacks_and_share(): void
    {
        $distribution = $this->metrics->categoryDistribution(25_000, 100_000, 100, 125);

        $this->assertSame(250.0, $distribution['value_per_hectare']);
        $this->assertSame(2.0, $distribution['sacks_per_hectare']);
        $this->assertSame(25.0, $distribution['share_percentage']);
    }
}
