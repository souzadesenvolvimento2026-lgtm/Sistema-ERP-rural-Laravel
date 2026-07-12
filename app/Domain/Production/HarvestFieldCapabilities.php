<?php

namespace App\Domain\Production;

final class HarvestFieldCapabilities
{
    /**
     * @return array{
     *     can_finalize: bool,
     *     can_reopen: bool,
     *     block_reason: ?string,
     *     status_label: string,
     *     status_tone: string
     * }
     */
    public function for(bool $isFinalized, int $loadCount): array
    {
        $canFinalize = ! $isFinalized && $loadCount > 0;

        return [
            'can_finalize' => $canFinalize,
            'can_reopen' => $isFinalized,
            'block_reason' => match (true) {
                $isFinalized => 'O talhao ja esta finalizado nesta safra.',
                $loadCount <= 0 => 'Lance pelo menos uma carga antes de finalizar o talhao.',
                default => null,
            },
            'status_label' => $isFinalized ? 'Finalizado' : 'Aberto',
            'status_tone' => $isFinalized ? 'success' : 'warning',
        ];
    }
}
