<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class FilterPanelUiTest extends TestCase
{
    public function testReusableFilterPanelContainsStandardControls(): void
    {
        $content = $this->contents('resources/views/partials/filter-panel.blade.php');

        $this->assertStringContainsString('$filterTitle = $title ?? \'Filtros\'', $content);
        $this->assertStringContainsString('class="ff-filter-form"', $content);
        $this->assertStringContainsString('class="ff-filter-grid"', $content);
        $this->assertStringContainsString('{{ $filterClearLabel }}', $content);
        $this->assertStringContainsString('{{ $filterSubmitLabel }}', $content);
        $this->assertStringContainsString('type="submit"', $content);
    }

    public function testFinanceiroUsesSingleSearchFieldInTheFilterPanel(): void
    {
        $content = $this->contents('resources/views/financeiro/index.blade.php');

        $this->assertStringContainsString('@include(\'partials.filter-panel\'', $content);
        $this->assertStringContainsString('data-ff-ledger-search', $content);
        $this->assertStringNotContainsString('ff-finance-datatable-search', $content);
        $this->assertStringNotContainsString('ff-finance-filter-strip', $content);
    }

    public function testComprasPedidosUsesSingleSearchFieldInTheFilterPanel(): void
    {
        $content = $this->contents('resources/views/compras/pedidos/index.blade.php');

        $this->assertStringContainsString('@include(\'partials.filter-panel\'', $content);
        $this->assertStringContainsString('data-ff-purchase-search', $content);
        $this->assertStringNotContainsString('ff-purchase-datatable-search', $content);
        $this->assertStringNotContainsString('ff-purchase-filter-card', $content);
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents, "Não foi possível ler {$path}.");

        return $contents;
    }
}
