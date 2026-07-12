<?php

namespace App\Domain\Access;

final class ProfileAccess
{
    private const SYSTEM_ADMINISTRATORS = ['administrador_sistema', 'gerencia_sistema'];

    private const SUPPORT_AGENTS = ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'];

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
}
