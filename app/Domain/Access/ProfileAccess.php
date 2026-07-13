<?php

namespace App\Domain\Access;

final class ProfileAccess
{
    private const SYSTEM_ADMINISTRATORS = ['administrador_sistema', 'gerencia_sistema'];

    private const SUPPORT_AGENTS = ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'];

    private const PROPERTY_FINANCE_MANAGERS = [
        'administrador',
        'administrador_sistema',
        'financeiro',
        'gerencia_sistema',
        'gestao',
        'gestor_financeiro',
        'gestor_propriedade',
    ];

    public function isSystemAdministrator(?string $profile): bool
    {
        return in_array((string) $profile, self::SYSTEM_ADMINISTRATORS, true);
    }

    public function canHandleSupport(?string $profile): bool
    {
        return in_array((string) $profile, self::SUPPORT_AGENTS, true);
    }

    public function hasGlobalPropertyAccess(?string $profile): bool
    {
        return in_array((string) $profile, self::SUPPORT_AGENTS, true);
    }

    public function canUseClientSupport(?string $profile, int $userId): bool
    {
        return $userId > 0 && ! $this->canHandleSupport($profile);
    }

    public function canManagePropertyFinance(?string $profile): bool
    {
        return in_array((string) $profile, self::PROPERTY_FINANCE_MANAGERS, true);
    }
}
