<?php

namespace Tests\Unit\Domain;

use App\Domain\Geo\TalhaoMapCapabilities;
use PHPUnit\Framework\TestCase;

class TalhaoMapCapabilitiesTest extends TestCase
{
    public function test_planning_and_ongoing_crops_block_map_mutations(): void
    {
        $rules = new TalhaoMapCapabilities;

        foreach (['planejamento', 'em_andamento'] as $status) {
            $capabilities = $rules->for([
                ['nome' => 'Soja 2026', 'status' => $status],
            ], 1);

            $this->assertFalse($capabilities['can_edit_geometry']);
            $this->assertFalse($capabilities['can_add_exclusion']);
            $this->assertFalse($capabilities['can_clear_exclusions']);
            $this->assertStringContainsString('Soja 2026', (string) $capabilities['block_reason']);
        }
    }

    public function test_terminal_crops_do_not_block_map_mutations(): void
    {
        $rules = new TalhaoMapCapabilities;

        foreach (['colhida', 'encerrada'] as $status) {
            $capabilities = $rules->for([
                ['nome' => 'Milho finalizado', 'status' => $status],
            ], 1);

            $this->assertTrue($capabilities['can_edit_geometry']);
            $this->assertTrue($capabilities['can_add_exclusion']);
            $this->assertTrue($capabilities['can_clear_exclusions']);
            $this->assertNull($capabilities['block_reason']);
        }
    }

    public function test_clear_exclusions_requires_an_existing_exclusion(): void
    {
        $capabilities = (new TalhaoMapCapabilities)->for([], 0);

        $this->assertTrue($capabilities['can_edit_geometry']);
        $this->assertTrue($capabilities['can_add_exclusion']);
        $this->assertFalse($capabilities['can_clear_exclusions']);
        $this->assertNull($capabilities['block_reason']);
    }
}
