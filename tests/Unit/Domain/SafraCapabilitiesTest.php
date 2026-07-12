<?php

namespace Tests\Unit\Domain;

use App\Domain\Production\SafraCapabilities;
use PHPUnit\Framework\TestCase;

class SafraCapabilitiesTest extends TestCase
{
    public function test_active_and_archived_seasons_receive_the_correct_listing_action(): void
    {
        $rules = new SafraCapabilities;

        $this->assertSame('encerrada', $rules->for('em_andamento', [])['actions'][0]['target_status']);
        $this->assertSame('planejamento', $rules->for('encerrada', [])['actions'][0]['target_status']);
        $this->assertTrue($rules->canTransition('em_andamento', 'encerrada'));
        $this->assertFalse($rules->canTransition('encerrada', 'colhida'));
    }

    public function test_delete_is_blocked_when_the_season_has_launched_data(): void
    {
        $rules = new SafraCapabilities;

        $this->assertTrue($rules->for('planejamento', [])['can_delete']);
        $this->assertFalse($rules->for('planejamento', ['despesas' => 1])['can_delete']);
    }
}
