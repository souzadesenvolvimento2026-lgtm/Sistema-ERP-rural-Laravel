<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class FinanceiroUiTest extends TestCase
{
    public function test_bank_transfer_forms_use_relative_actions_and_split_footer(): void
    {
        $contasView = $this->contents('resources/views/financeiro/contas/index.blade.php');
        $launchModal = $this->contents('resources/views/financeiro/partials/novo-lancamento-modal.blade.php');
        $controller = $this->contents('app/Http/Controllers/ContaBancariaController.php');
        $css = $this->contents('public/css/farmfort.css');

        $this->assertStringContainsString("route('financeiro.contas.transfer', [], false)", $contasView);
        $this->assertStringContainsString("route('financeiro.contas.transfer', [], false)", $launchModal);
        $this->assertStringContainsString("route('financeiro.contas.store', [], false)", $contasView);
        $this->assertStringContainsString("route('financeiro.contas.update', \$conta->id, false)", $contasView);
        $this->assertStringContainsString("route('financeiro.contas.transfer.update', \$transferencia->id, false)", $contasView);
        $this->assertStringContainsString("->to(route(\$dados['return_route'] ?? 'financeiro.contas.index', [], false))", $controller);
        $this->assertStringNotContainsString("->route(\$dados['return_route'] ?? 'financeiro.contas.index')", $controller);

        $this->assertStringContainsString('modal-footer ff-modal-footer-split', $contasView);
        $this->assertStringContainsString('modal-footer ff-modal-footer-split', $launchModal);
        $this->assertStringContainsString('ff-bank-transfer-value', $contasView);
        $this->assertStringContainsString('ff-bank-transfer-inline-values', $contasView);
        $this->assertStringContainsString('Transferido', $contasView);
        $this->assertStringContainsString('Depositado', $contasView);
        $this->assertStringContainsString('.ff-bank-modal .form-control', $css);
        $this->assertStringContainsString('.ff-bank-modal .form-select', $css);
        $this->assertStringContainsString('.ff-bank-transfer-value', $css);
        $this->assertStringContainsString('.ff-bank-transfer-inline-values', $css);
        $this->assertStringContainsString('.ff-transfer-value-out strong', $css);
        $this->assertStringContainsString('.ff-transfer-value-in strong', $css);
        $this->assertStringContainsString('width: min(94vw, 720px);', $css);
    }

    public function test_dashboard_hero_keeps_the_same_visual_identity_in_every_theme(): void
    {
        $css = $this->contents('public/css/farmfort.css');

        $this->assertStringContainsString('.ff-bi-hero {', $css);
        $this->assertStringContainsString('linear-gradient(135deg, rgba(3, 98, 62, .92), rgba(44, 111, 158, .78))', $css);
        $this->assertStringNotContainsString('html[data-theme="light"] .ff-bi-hero', $css);
        $this->assertStringNotContainsString('html[data-theme="dark"] .ff-bi-hero', $css);
    }

    public function test_financeiro_index_prioritizes_ledger_and_removes_secondary_blocks(): void
    {
        $view = $this->contents('resources/views/financeiro/index.blade.php');
        $css = $this->contents('public/css/farmfort.css');

        $this->assertStringContainsString('ff-finance-ledger-priority', $view);
        $this->assertStringContainsString('data-ff-finance-priority="lancamentos"', $view);
        $this->assertStringContainsString('ff-row-action-toggle', $view);
        $this->assertStringContainsString('ff-row-action-primary ff-row-action-approve', $view);
        $this->assertStringContainsString('ff-row-action-primary ff-row-action-pay', $view);
        $this->assertStringContainsString('ff-row-action-primary ff-row-action-receive', $view);
        $this->assertStringNotContainsString("include('financeiro.partials.agenda')", $view);
        $this->assertStringNotContainsString("include('financeiro.partials.contas')", $view);
        $this->assertStringNotContainsString('Agenda financeira', $view);
        $this->assertStringNotContainsString('Saldos por conta', $view);

        $this->assertStringContainsString('.ff-finance-ledger-priority', $css);
        $this->assertStringContainsString('.ff-row-actions .ff-row-action-primary', $css);
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertIsString($contents, "Nao foi possivel ler {$path}.");

        return $contents;
    }
}
