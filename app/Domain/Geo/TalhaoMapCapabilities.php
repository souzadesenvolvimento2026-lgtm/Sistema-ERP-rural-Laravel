<?php

namespace App\Domain\Geo;

final class TalhaoMapCapabilities
{
    private const BLOCKING_STATUSES = ['planejamento', 'em_andamento'];

    /**
     * @param  list<array{nome?: string|null, status?: string|null}>  $safras
     * @return array{
     *     can_edit_geometry: bool,
     *     can_add_exclusion: bool,
     *     can_clear_exclusions: bool,
     *     block_reason: ?string
     * }
     */
    public function for(array $safras, int $exclusionCount = 0): array
    {
        $blockingSafras = array_values(array_filter(
            $safras,
            fn (array $safra): bool => $this->blocksStatus($safra['status'] ?? null),
        ));
        $canChangeMap = $blockingSafras === [];

        return [
            'can_edit_geometry' => $canChangeMap,
            'can_add_exclusion' => $canChangeMap,
            'can_clear_exclusions' => $canChangeMap && $exclusionCount > 0,
            'block_reason' => $canChangeMap ? null : $this->blockReason($blockingSafras),
        ];
    }

    /**
     * @return list<string>
     */
    public function blockingStatuses(): array
    {
        return self::BLOCKING_STATUSES;
    }

    public function blocksStatus(?string $status): bool
    {
        return in_array((string) $status, self::BLOCKING_STATUSES, true);
    }

    /**
     * @param  list<array{nome?: string|null, status?: string|null}>  $safras
     */
    private function blockReason(array $safras): string
    {
        $names = array_values(array_unique(array_filter(array_map(
            fn (array $safra): string => trim((string) ($safra['nome'] ?? '')),
            $safras,
        ))));
        $statusLabels = array_values(array_unique(array_filter(array_map(
            fn (array $safra): ?string => match ((string) ($safra['status'] ?? '')) {
                'planejamento' => 'em planejamento',
                'em_andamento' => 'em andamento',
                default => null,
            },
            $safras,
        ))));
        $statusLabel = $statusLabels === []
            ? 'em andamento'
            : (count($statusLabels) === 1 ? $statusLabels[0] : 'em planejamento ou em andamento');

        if ($names === []) {
            return 'Este talhão não pode ser editado por estar vinculado a uma safra '.$statusLabel.'.';
        }

        $safraLabel = count($names) === 1 ? 'à safra '.$names[0] : 'às safras '.implode(', ', $names);

        return 'Este talhão não pode ser editado por estar vinculado '.$safraLabel.' '.$statusLabel.'.';
    }
}
