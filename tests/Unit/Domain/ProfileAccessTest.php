<?php

namespace Tests\Unit\Domain;

use App\Domain\Access\ProfileAccess;
use PHPUnit\Framework\TestCase;

class ProfileAccessTest extends TestCase
{
    private ProfileAccess $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = new ProfileAccess;
    }

    public function test_only_system_administrators_receive_system_administration_access(): void
    {
        $this->assertTrue($this->access->isSystemAdministrator('administrador_sistema'));
        $this->assertTrue($this->access->isSystemAdministrator('gerencia_sistema'));
        $this->assertFalse($this->access->isSystemAdministrator('colaborador_sistema'));
        $this->assertFalse($this->access->isSystemAdministrator('administrador'));
    }

    public function test_support_agents_and_clients_are_classified_consistently(): void
    {
        $this->assertTrue($this->access->canHandleSupport('colaborador_sistema'));
        $this->assertFalse($this->access->canUseClientSupport('colaborador_sistema', 10));
        $this->assertTrue($this->access->canUseClientSupport('gestor_propriedade', 10));
        $this->assertFalse($this->access->canUseClientSupport('gestor_propriedade', 0));
    }

    public function test_only_internal_profiles_have_global_property_access(): void
    {
        $this->assertTrue($this->access->hasGlobalPropertyAccess('administrador_sistema'));
        $this->assertTrue($this->access->hasGlobalPropertyAccess('gerencia_sistema'));
        $this->assertTrue($this->access->hasGlobalPropertyAccess('colaborador_sistema'));
        $this->assertFalse($this->access->hasGlobalPropertyAccess('gestor_propriedade'));
    }

    public function test_financial_profile_can_manage_property_finance_like_property_manager(): void
    {
        $this->assertTrue($this->access->canManagePropertyFinance('gestor_propriedade'));
        $this->assertTrue($this->access->canManagePropertyFinance('financeiro'));
        $this->assertFalse($this->access->canManagePropertyFinance('visualizador'));
    }
}
