<?php

namespace App\Domain\Property;

final class FarmGroupEligibility
{
    /**
     * @return array{eligible_for_group: bool, group_ineligibility_reason: ?string}
     */
    public function for(bool $isActive, ?string $plan): array
    {
        if (! $isActive) {
            return [
                'eligible_for_group' => false,
                'group_ineligibility_reason' => 'A fazenda precisa estar ativa.',
            ];
        }

        if ($plan !== 'premium') {
            return [
                'eligible_for_group' => false,
                'group_ineligibility_reason' => 'Grupos de fazendas exigem o plano Premium.',
            ];
        }

        return [
            'eligible_for_group' => true,
            'group_ineligibility_reason' => null,
        ];
    }
}
