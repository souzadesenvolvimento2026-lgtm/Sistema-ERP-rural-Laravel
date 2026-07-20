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

    public function test_stock_index_exposes_safe_indicators_and_visual_situation(): void
    {
        $service = file_get_contents(base_path('app/Services/ProdutoService.php'));
        $view = file_get_contents(base_path('resources/views/produtos/partials/tabela.blade.php'));

        $this->assertStringContainsString('cardsEstoque', $service);
        $this->assertStringContainsString('Produtos cadastrados', $service);
        $this->assertStringContainsString('Valor total estimado', $service);
        $this->assertStringContainsString('produtosArmazenados', $service);
        $this->assertStringContainsString('saldo_estoque_raw > 0.0', $service);
        $this->assertStringContainsString("'rows' => \$produtosArmazenados", $service);
        $this->assertStringContainsString("'cards' => \$this->cardsEstoque(\$rows)", $service);
        $this->assertStringContainsString('situacaoEstoque', $service);
        $this->assertStringContainsString('custo_medio', $service);
        $this->assertStringContainsString('Produtos armazenados', $view);
        $this->assertStringContainsString('Custo médio', $view);
        $this->assertStringContainsString('Valor total', $view);
        $this->assertStringContainsString('Situação', $view);
        $this->assertStringContainsString('produtos.movimentos.store', $view);
    }

    public function test_purchase_order_reuses_existing_product_before_generating_internal_code(): void
    {
        $service = file_get_contents(base_path('app/Services/CompraPedidoService.php'));

        $this->assertStringContainsString('findMatchingStockProduct', $service);
        $this->assertStringContainsString('descriptionsMatch', $service);
        $this->assertStringContainsString('codigo_fornecedor', $service);
        $this->assertStringContainsString('internalCodeForId', $service);
        $this->assertStringContainsString("'codigo_interno' => null", $service);
    }
}
