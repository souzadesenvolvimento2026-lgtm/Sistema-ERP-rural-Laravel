<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class CompraFornecedorUiTest extends TestCase
{
    public function testPurchaseOrderModalUsesSupplierSelectorAndCompactInvoiceCheckbox(): void
    {
        $content = file_get_contents('resources/views/compras/pedidos/partials/create-modal.blade.php');

        self::assertStringContainsString('data-purchase-supplier-select', $content);
        self::assertStringContainsString('ff-purchase-nf-check', $content);
        self::assertStringContainsString('ff-purchase-nf-checkbox', $content);
        self::assertStringNotContainsString('form-check d-flex align-items-start gap-2 m-0', $content);
    }

    public function testPurchasesExposeSuppliersTabAndPropertyScopedService(): void
    {
        $routes = file_get_contents('routes/web.php');
        $tabs = file_get_contents('resources/views/compras/partials/tabs.blade.php');
        $service = file_get_contents('app/Services/CompraFornecedorService.php');
        $viewExists = file_exists('resources/views/compras/fornecedores/index.blade.php');

        self::assertStringContainsString('CompraFornecedorController', $routes);
        self::assertStringContainsString('compras.fornecedores.index', $tabs);
        self::assertStringContainsString("where('propriedade_id', \$propertyId)", $service);
        self::assertStringContainsString('AuditService::log', $service);
        self::assertTrue($viewExists);
    }

    public function testPurchaseOrderServiceCanLoadActiveSuppliers(): void
    {
        $service = file_get_contents('app/Services/CompraPedidoService.php');

        self::assertStringContainsString("'fornecedores' => \$this->activeSuppliers(\$propertyId)", $service);
        self::assertStringContainsString('supplierPayload', $service);
    }
}
