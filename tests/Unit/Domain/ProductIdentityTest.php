<?php

namespace Tests\Unit\Domain;

use App\Domain\Inventory\ProductIdentity;
use PHPUnit\Framework\TestCase;

class ProductIdentityTest extends TestCase
{
    public function test_internal_product_code_is_generated_from_product_id(): void
    {
        $identity = new ProductIdentity;

        $this->assertSame('PRD-000123', $identity->internalCodeForId(123));
    }

    public function test_product_description_matching_ignores_case_accents_and_extra_spaces(): void
    {
        $identity = new ProductIdentity;
        $accentedDescription = "C\u{00E2}mera de Seguran\u{00E7}a Vip 1230b";

        $this->assertTrue($identity->descriptionsMatch($accentedDescription, '  camera   DE seguranca vip 1230B '));
        $this->assertFalse($identity->descriptionsMatch('', 'Produto sem descricao'));
    }
}
