<?php

namespace Tests\Unit\Domain;

use App\Domain\Fiscal\DocumentCapabilities;
use PHPUnit\Framework\TestCase;

class DocumentCapabilitiesTest extends TestCase
{
    public function test_document_actions_follow_the_allowed_status_transitions(): void
    {
        $rules = new DocumentCapabilities;

        $pending = $rules->for('pendente');
        $this->assertSame(['conferido', 'arquivado'], $pending['allowed_transitions']);
        $this->assertSame(['conferir', 'status'], array_column($pending['actions'], 'action'));
        $this->assertTrue($rules->canTransition('conferido', 'arquivado'));
        $this->assertFalse($rules->canTransition('arquivado', 'conferido'));
    }
}
