<?php

namespace App\Domain\Fiscal;

final class DocumentCapabilities
{
    private const TRANSITIONS = [
        'pendente' => ['conferido', 'arquivado'],
        'conferido' => ['pendente', 'arquivado'],
        'arquivado' => ['pendente'],
    ];

    /**
     * @return list<string>
     */
    public function allowedTransitions(?string $status): array
    {
        return self::TRANSITIONS[(string) $status] ?? [];
    }

    public function canTransition(?string $currentStatus, string $targetStatus): bool
    {
        return $currentStatus === $targetStatus
            || in_array($targetStatus, $this->allowedTransitions($currentStatus), true);
    }

    /**
     * @return array{
     *     allowed_transitions: list<string>,
     *     actions: list<array{target_status: string, label: string, action: string}>,
     *     status_tone: string
     * }
     */
    public function for(?string $status): array
    {
        $transitions = $this->allowedTransitions($status);

        return [
            'allowed_transitions' => $transitions,
            'actions' => array_map(
                fn (string $targetStatus): array => [
                    'target_status' => $targetStatus,
                    'label' => match ($targetStatus) {
                        'conferido' => 'Conferir',
                        'arquivado' => 'Arquivar',
                        default => 'Pendente',
                    },
                    'action' => $targetStatus === 'conferido' ? 'conferir' : 'status',
                ],
                $transitions,
            ),
            'status_tone' => $status === 'conferido' ? 'open' : '',
        ];
    }
}
