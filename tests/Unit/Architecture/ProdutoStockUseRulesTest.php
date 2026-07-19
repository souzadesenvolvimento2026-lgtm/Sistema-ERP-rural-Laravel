<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class ProdutoStockUseRulesTest extends TestCase
{
    public function test_patrimonio_stock_output_requires_justification_only_when_no_safra(): void
    {
        $service = file_get_contents(base_path('app/Services/ProdutoService.php'));

        $this->assertStringContainsString('$destinoTipo === \'safra\' && $safraId === null', $service);
        $this->assertStringContainsString(
            '$destinoTipo === \'patrimonio\' && $safraId === null && $justificativaSemSafra === \'\'',
            $service
        );
        $this->assertStringContainsString('justificativa_sem_safra', $service);
        $this->assertStringContainsString("'origem_id' => \$produtoId", $service);
        $this->assertStringNotContainsString(
            'in_array($destinoTipo, [\'safra\', \'patrimonio\'], true) && $safraId === null',
            $service
        );
    }

    public function test_stock_output_modal_exposes_optional_safra_justification(): void
    {
        $view = file_get_contents(base_path('resources/views/produtos/partials/baixa-modal.blade.php'));

        $this->assertStringContainsString('Safra (opcional)', $view);
        $this->assertStringContainsString('justificativa_sem_safra', $view);
        $this->assertStringContainsString('patrimonioSemSafra', $view);
        $this->assertStringContainsString('safraSelect.required = value === \'safra\'', $view);
    }
}
