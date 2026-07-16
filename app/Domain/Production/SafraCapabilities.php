<?php

namespace App\Domain\Production;

final class SafraCapabilities
{
    private const VALID_STATUSES = ['planejamento', 'em_andamento', 'colhida', 'encerrada'];

    /**
     * @return list<string>
     */
    public function allowedTransitions(?string $status): array
    {
        return match ((string) $status) {
            'encerrada' => ['planejamento'],
            'planejamento' => ['em_andamento', 'encerrada'],
            'em_andamento', 'colhida' => ['encerrada'],
            default => [],
        };
    }

    public function canTransition(?string $currentStatus, string $targetStatus): bool
    {
        return in_array($targetStatus, self::VALID_STATUSES, true)
            && in_array($targetStatus, $this->allowedTransitions($currentStatus), true);
    }

    /**
     * @param  array<string, int>  $launchedData
     * @return array{
     *     allowed_transitions: list<string>,
     *     actions: list<array{target_status: string, label: string}>,
     *     can_delete: bool,
     *     delete_block_reason: ?string,
     *     status_tone: string
     * }
     */
    public function for(?string $status, array $launchedData): array
    {
        $transitions = $this->allowedTransitions($status);
        $canDelete = $launchedData === [];

        return [
            'allowed_transitions' => $transitions,
            'actions' => array_map(
                fn (string $targetStatus): array => [
                    'target_status' => $targetStatus,
                    'label' => match ($targetStatus) {
                        'em_andamento' => 'Iniciar',
                        'encerrada' => 'Arquivar',
                        default => 'Desarquivar',
                    },
                ],
                $transitions,
            ),
            'can_delete' => $canDelete,
            'delete_block_reason' => $canDelete
                ? null
                : 'Não pode excluir: existem dados lançados nesta safra.',
            'status_tone' => in_array((string) $status, ['colhida', 'encerrada'], true)
                ? 'success'
                : ((string) $status === 'em_andamento' ? 'success' : 'warning'),
        ];
    }
}
