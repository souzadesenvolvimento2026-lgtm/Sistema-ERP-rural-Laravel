<?php

namespace Tests\Unit\Domain;

use App\Domain\Purchasing\PurchaseOrderCapabilities;
use PHPUnit\Framework\TestCase;

class PurchaseOrderCapabilitiesTest extends TestCase
{
    public function test_pending_orders_expose_mutation_and_approval_capabilities(): void
    {
        $capabilities = (new PurchaseOrderCapabilities)->for('em_aberto', true, 1);

        $this->assertTrue($capabilities['can_edit']);
        $this->assertTrue($capabilities['can_approve']);
        $this->assertTrue($capabilities['can_link_invoice']);
        $this->assertTrue($capabilities['can_unlink_invoice']);
        $this->assertTrue($capabilities['can_confirm_invoice_link']);
    }

    public function test_invoice_confirmation_requires_a_matching_preview_and_mutable_status(): void
    {
        $rules = new PurchaseOrderCapabilities;

        $this->assertFalse($rules->for('em_aberto', true, 0)['can_confirm_invoice_link']);
        $this->assertFalse($rules->for('em_aberto', false, 1)['can_confirm_invoice_link']);
        $this->assertFalse($rules->for('aprovado_baixado', true, 1)['can_confirm_invoice_link']);
        $this->assertFalse($rules->for('aprovado_baixado')['can_edit']);
    }
}
