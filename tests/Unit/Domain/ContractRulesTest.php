<?php

namespace Tests\Unit\Domain;

use App\Domain\Production\ContractRules;
use PHPUnit\Framework\TestCase;

class ContractRulesTest extends TestCase
{
    private ContractRules $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new ContractRules;
    }

    public function test_informed_total_has_precedence_over_calculated_total(): void
    {
        $this->assertSame(750.0, $this->rules->totalValue(10, 50, 750));
    }

    public function test_total_is_calculated_when_informed_total_is_not_positive(): void
    {
        $this->assertSame(500.0, $this->rules->totalValue(10, 50));
    }

    public function test_delivery_status_changes_only_after_the_contracted_quantity_is_reached(): void
    {
        $this->assertSame('parcial', $this->rules->deliveryStatus(99, 100));
        $this->assertSame('entregue', $this->rules->deliveryStatus(100, 100));
        $this->assertSame('entregue', $this->rules->deliveryStatus(110, 100));
    }

    public function test_delivery_progress_is_capped_and_closed_contracts_reject_new_deliveries(): void
    {
        $this->assertSame(100.0, $this->rules->deliveryProgress(120, 100));
        $this->assertFalse($this->rules->canRegisterDelivery('entregue'));
        $this->assertFalse($this->rules->canRegisterDelivery('cancelado'));
        $this->assertTrue($this->rules->canRegisterDelivery('parcial'));
    }
}
