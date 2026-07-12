<?php

namespace App\Domain\Purchasing;

final class PurchaseOrderCapabilities
{
    private const MUTABLE_STATUSES = ['em_aberto', 'aguardando_aprovacao'];

    /**
     * @return array{
     *     can_edit: bool,
     *     can_approve: bool,
     *     can_link_invoice: bool,
     *     can_unlink_invoice: bool,
     *     can_confirm_invoice_link: bool
     * }
     */
    public function for(
        ?string $status,
        bool $previewBelongsToOrder = false,
        int $previewMatchCount = 0,
    ): array {
        $isMutable = in_array((string) $status, self::MUTABLE_STATUSES, true);

        return [
            'can_edit' => $isMutable,
            'can_approve' => $isMutable,
            'can_link_invoice' => $isMutable,
            'can_unlink_invoice' => $isMutable,
            'can_confirm_invoice_link' => $isMutable
                && $previewBelongsToOrder
                && $previewMatchCount > 0,
        ];
    }
}
