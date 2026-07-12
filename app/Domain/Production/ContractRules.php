<?php

namespace App\Domain\Production;

final class ContractRules
{
    private const CLOSED_STATUSES = ['entregue', 'cancelado'];

    public function totalValue(
        float|int $quantity,
        float|int $unitPrice,
        float|int $informedTotal = 0,
    ): float {
        $informedTotal = max(0.0, (float) $informedTotal);

        if ($informedTotal > 0.0) {
            return $informedTotal;
        }

        return max(0.0, (float) $quantity) * max(0.0, (float) $unitPrice);
    }

    public function deliveryStatus(float|int $delivered, float|int $contracted): string
    {
        $contracted = max(0.0, (float) $contracted);

        return $contracted > 0.0 && (float) $delivered >= $contracted
            ? 'entregue'
            : 'parcial';
    }

    public function deliveryProgress(float|int $delivered, float|int $contracted): float
    {
        $contracted = max(0.0, (float) $contracted);

        if ($contracted == 0.0) {
            return 0.0;
        }

        return min(100.0, max(0.0, ((float) $delivered / $contracted) * 100));
    }

    public function canRegisterDelivery(string $status): bool
    {
        return ! in_array($status, self::CLOSED_STATUSES, true);
    }
}
