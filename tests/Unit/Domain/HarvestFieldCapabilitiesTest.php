<?php

namespace Tests\Unit\Domain;

use App\Domain\Production\HarvestFieldCapabilities;
use PHPUnit\Framework\TestCase;

class HarvestFieldCapabilitiesTest extends TestCase
{
    public function test_open_field_requires_a_load_before_finalization(): void
    {
        $rules = new HarvestFieldCapabilities;

        $blocked = $rules->for(false, 0);
        $this->assertFalse($blocked['can_finalize']);
        $this->assertNotNull($blocked['block_reason']);
        $this->assertTrue($rules->for(false, 1)['can_finalize']);
    }

    public function test_only_a_finalized_field_can_be_reopened(): void
    {
        $rules = new HarvestFieldCapabilities;

        $this->assertFalse($rules->for(false, 1)['can_reopen']);
        $this->assertTrue($rules->for(true, 1)['can_reopen']);
        $this->assertFalse($rules->for(true, 1)['can_finalize']);
    }
}
