<?php

namespace Tests\Unit\Domain;

use App\Domain\Property\FarmGroupEligibility;
use PHPUnit\Framework\TestCase;

class FarmGroupEligibilityTest extends TestCase
{
    public function test_only_active_premium_farms_are_eligible_for_groups(): void
    {
        $rules = new FarmGroupEligibility;

        $this->assertTrue($rules->for(true, 'premium')['eligible_for_group']);
        $this->assertFalse($rules->for(true, 'basico')['eligible_for_group']);
        $this->assertFalse($rules->for(false, 'premium')['eligible_for_group']);
        $this->assertNotNull($rules->for(true, 'basico')['group_ineligibility_reason']);
    }
}
