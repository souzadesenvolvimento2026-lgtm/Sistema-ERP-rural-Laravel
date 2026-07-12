<?php

namespace App\Domain\Finance;

final class FinancialWorkflowRules
{
    /**
     * @return array{
     *     can_select_for_batch: bool,
     *     can_approve: bool,
     *     can_reject: bool,
     *     can_pay: bool,
     *     can_cancel: bool
     * }
     */
    public function expenseCapabilities(
        string $paymentStatus,
        string $approvalStatus,
        bool $hasDeletionRequest = false
    ): array {
        $isActive = $paymentStatus !== 'cancelado';
        $awaitingApproval = $isActive
            && $approvalStatus === 'pendente'
            && ($paymentStatus !== 'pago' || $hasDeletionRequest);

        return [
            'can_select_for_batch' => $awaitingApproval && $paymentStatus !== 'pago',
            'can_approve' => $awaitingApproval,
            'can_reject' => $awaitingApproval,
            'can_pay' => $approvalStatus === 'aprovada'
                && in_array($paymentStatus, ['pendente', 'vencido'], true),
            'can_cancel' => $isActive,
        ];
    }

    /**
     * @return array{
     *     can_select_for_batch: bool,
     *     can_approve: bool,
     *     can_reject: bool,
     *     can_receive: bool,
     *     can_cancel: bool
     * }
     */
    public function revenueCapabilities(
        string $receiptStatus,
        string $approvalStatus,
        bool $hasDeletionRequest = false
    ): array {
        $isActive = $receiptStatus !== 'cancelado';
        $awaitingApproval = $isActive
            && $approvalStatus === 'pendente'
            && ($receiptStatus !== 'recebido' || $hasDeletionRequest);

        return [
            'can_select_for_batch' => $awaitingApproval && $receiptStatus !== 'recebido',
            'can_approve' => $awaitingApproval,
            'can_reject' => $awaitingApproval,
            'can_receive' => $approvalStatus === 'aprovada' && $receiptStatus === 'pendente',
            'can_cancel' => $isActive,
        ];
    }
}
