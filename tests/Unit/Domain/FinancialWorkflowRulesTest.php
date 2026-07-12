<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\FinancialWorkflowRules;
use PHPUnit\Framework\TestCase;

class FinancialWorkflowRulesTest extends TestCase
{
    private FinancialWorkflowRules $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new FinancialWorkflowRules;
    }

    public function test_pending_expense_can_be_selected_approved_and_rejected(): void
    {
        $this->assertSame([
            'can_select_for_batch' => true,
            'can_approve' => true,
            'can_reject' => true,
            'can_pay' => false,
            'can_cancel' => true,
        ], $this->rules->expenseCapabilities('pendente', 'pendente'));
    }

    public function test_only_approved_open_expense_can_be_paid(): void
    {
        foreach (['pendente', 'vencido'] as $paymentStatus) {
            $capabilities = $this->rules->expenseCapabilities($paymentStatus, 'aprovada');

            $this->assertTrue($capabilities['can_pay']);
            $this->assertFalse($capabilities['can_approve']);
            $this->assertFalse($capabilities['can_reject']);
            $this->assertFalse($capabilities['can_select_for_batch']);
        }

        $this->assertFalse($this->rules->expenseCapabilities('pago', 'aprovada')['can_pay']);
        $this->assertFalse($this->rules->expenseCapabilities('pago', 'pendente')['can_approve']);
        $this->assertFalse($this->rules->expenseCapabilities('cancelado', 'aprovada')['can_cancel']);
    }

    public function test_pending_revenue_can_be_selected_approved_and_rejected(): void
    {
        $this->assertSame([
            'can_select_for_batch' => true,
            'can_approve' => true,
            'can_reject' => true,
            'can_receive' => false,
            'can_cancel' => true,
        ], $this->rules->revenueCapabilities('pendente', 'pendente'));
    }

    public function test_only_approved_pending_revenue_can_be_received(): void
    {
        $capabilities = $this->rules->revenueCapabilities('pendente', 'aprovada');

        $this->assertTrue($capabilities['can_receive']);
        $this->assertFalse($capabilities['can_approve']);
        $this->assertFalse($capabilities['can_reject']);
        $this->assertFalse($capabilities['can_select_for_batch']);
        $this->assertFalse($this->rules->revenueCapabilities('recebido', 'pendente')['can_select_for_batch']);
        $this->assertFalse($this->rules->revenueCapabilities('recebido', 'pendente')['can_approve']);
        $this->assertFalse($this->rules->revenueCapabilities('recebido', 'aprovada')['can_receive']);
        $this->assertFalse($this->rules->revenueCapabilities('cancelado', 'aprovada')['can_cancel']);
    }

    public function test_canceled_entries_expose_no_workflow_action(): void
    {
        $this->assertSame([
            'can_select_for_batch' => false,
            'can_approve' => false,
            'can_reject' => false,
            'can_pay' => false,
            'can_cancel' => false,
        ], $this->rules->expenseCapabilities('cancelado', 'pendente'));

        $this->assertSame([
            'can_select_for_batch' => false,
            'can_approve' => false,
            'can_reject' => false,
            'can_receive' => false,
            'can_cancel' => false,
        ], $this->rules->revenueCapabilities('cancelado', 'pendente'));
    }

    public function test_settled_entries_can_only_be_reviewed_for_a_deletion_request(): void
    {
        $paidExpense = $this->rules->expenseCapabilities('pago', 'pendente', true);
        $receivedRevenue = $this->rules->revenueCapabilities('recebido', 'pendente', true);

        $this->assertTrue($paidExpense['can_approve']);
        $this->assertTrue($paidExpense['can_reject']);
        $this->assertFalse($paidExpense['can_select_for_batch']);
        $this->assertTrue($receivedRevenue['can_approve']);
        $this->assertTrue($receivedRevenue['can_reject']);
        $this->assertFalse($receivedRevenue['can_select_for_batch']);
    }
}
