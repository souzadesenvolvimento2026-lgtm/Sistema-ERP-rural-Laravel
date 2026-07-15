<?php

namespace App\View\Composers;

use App\Domain\Access\ProfileAccess;
use App\Services\AuthenticationService;
use App\Services\SystemWriteUnlockService;
use Illuminate\View\View;

final class FarmfortLayoutComposer
{
    public function __construct(
        private readonly ProfileAccess $access,
        private readonly AuthenticationService $authentication,
        private readonly SystemWriteUnlockService $writeUnlock,
    ) {
    }

    public function compose(View $view): void
    {
        $data = $view->getData();
        $rawTitle = (string) ($data['title'] ?? 'Painel');
        $displayTitle = trim((string) preg_replace('/^FarmFort\s*-\s*/i', '', $rawTitle));
        $profile = (string) session('perfil', '');
        $userId = (int) session('usuario_id', 0);
        $isSystemAdmin = $this->access->isSystemAdministrator($profile);
        $propertyOptions = $userId > 0
            ? $this->authentication->propertyOptions($userId, $profile)
            : collect();
        $selectedPropertyId = (int) session('propriedade_id', 0);
        $systemWriteUnlocked = $isSystemAdmin && $this->writeUnlock->isActiveFor($selectedPropertyId);
        $menu = $this->mainMenu();
        if ($isSystemAdmin) {
            $menu = array_values(array_filter($menu, fn (array $item) => $item['key'] !== 'usuarios'));
        }
        $isFinanceSection = request()->routeIs(
            'financeiro.*',
            'relatorios.fluxo-caixa',
            'relatorios.dre',
            'relatorios.orcado-realizado',
            'relatorios.comparativo-safras.*',
        );

        $view->with([
            'displayTitle' => $displayTitle,
            'isFinanceSection' => $isFinanceSection,
            'active' => $isFinanceSection ? 'financeiro' : ($data['activeModule'] ?? 'dashboard'),
            'fullWidth' => (bool) ($data['fullWidth'] ?? false),
            'topbarLabel' => $data['topbarLabel'] ?? $displayTitle,
            'profile' => $profile,
            'isSystemAdmin' => $isSystemAdmin,
            'userName' => session('usuario_nome', session('nome', 'Usuário')),
            'propertyName' => $data['property']->nome ?? session('propriedade_nome', 'Fazenda teste'),
            'selectedPropertyId' => $selectedPropertyId,
            'propertyOptions' => $propertyOptions,
            'systemWriteUnlocked' => $systemWriteUnlocked,
            'systemWriteUnlockExpiresAt' => $systemWriteUnlocked ? $this->writeUnlock->expiresAt() : null,
            'adminMenu' => $this->adminMenu(),
            'menu' => $menu,
            'financeTabs' => $this->financeTabs(),
            'fiscalTabs' => $this->fiscalTabs(),
            'loggedUserId' => $userId,
            'canHandleSupport' => $this->access->canHandleSupport($profile),
            'canUseClientChat' => $this->access->canUseClientSupport($profile, $userId),
            'suporteEndpoint' => '/ajax/suporte-chat',
            'chatInternoEndpoint' => '/ajax/chat-interno',
        ]);
    }

    private function adminMenu(): array
    {
        return [
            ['key' => 'admin', 'label' => 'Painel Admin', 'icon' => 'bi-speedometer2', 'route' => route('admin.index')],
            ['key' => 'propriedades', 'label' => 'Propriedades', 'icon' => 'bi-map', 'route' => route('propriedades.index')],
            ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'bi-people-fill', 'route' => route('usuarios.index')],
            ['key' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'bi-shield-check', 'route' => route('auditoria.index')],
            ['key' => 'suporte', 'label' => 'Chat/Suporte', 'icon' => 'bi-chat-dots', 'route' => route('suporte.admin.index')],
        ];
    }

    private function mainMenu(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => route('dashboard')],
            ['key' => 'financeiro', 'label' => 'Financeiro', 'icon' => 'bi-cash-stack', 'route' => route('financeiro.index')],
            ['key' => 'fiscal', 'label' => 'Fiscal', 'icon' => 'bi-clipboard-check', 'route' => route('fiscal.index')],
            ['key' => 'compras', 'label' => 'Compras', 'icon' => 'bi-cart-check', 'route' => route('compras.index')],
            ['key' => 'patrimonio', 'label' => 'Patrimônio', 'icon' => 'bi-truck', 'route' => route('patrimonio.index')],
            ['key' => 'safras', 'label' => 'Safras', 'icon' => 'bi-calendar3', 'route' => route('safras.index')],
            ['key' => 'talhoes', 'label' => 'Talhões', 'icon' => 'bi-grid-3x3-gap', 'route' => route('talhoes.mapa')],
            ['key' => 'colheita', 'label' => 'Colheita', 'icon_svg' => 'assets/icons/colheitadeira.svg', 'route' => route('colheita.index')],
            ['key' => 'estoque-produtos', 'label' => 'Estoque de produtos', 'icon' => 'bi-box-seam', 'route' => route('produtos.index')],
            ['key' => 'estoque-producao', 'label' => 'Estoque de produção', 'icon_svg' => 'assets/icons/silo-graos.svg', 'route' => route('estoque-producao.index')],
            ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'bi-people-fill', 'route' => route('usuarios.index')],
            ['key' => 'relatorios', 'label' => 'Indicadores e relatórios', 'icon' => 'bi-clipboard-data-fill', 'route' => route('relatorios.index')],
        ];
    }

    private function financeTabs(): array
    {
        return $this->activeTabs([
            ['route' => 'financeiro.index', 'icon' => 'bi-plus-circle', 'label' => 'Lançamentos', 'patterns' => ['financeiro.index', 'financeiro.despesas.*', 'financeiro.receitas.*', 'financeiro.lancamentos.*']],
            ['route' => 'relatorios.fluxo-caixa', 'icon' => 'bi-graph-up-arrow', 'label' => 'Fluxo de Caixa', 'patterns' => ['relatorios.fluxo-caixa']],
            ['route' => 'relatorios.dre', 'icon' => 'bi-bar-chart-line', 'label' => 'DRE', 'patterns' => ['relatorios.dre']],
            ['route' => 'relatorios.orcado-realizado', 'icon' => 'bi-table', 'label' => 'Orçado x Realizado', 'patterns' => ['relatorios.orcado-realizado']],
            ['route' => 'financeiro.analise-despesas.index', 'icon' => 'bi-pie-chart', 'label' => 'DRE Agrícola', 'patterns' => ['financeiro.analise-despesas.*']],
            ['route' => 'relatorios.comparativo-safras.index', 'icon' => 'bi-columns-gap', 'label' => 'Comparativo de Safras', 'patterns' => ['relatorios.comparativo-safras.*']],
        ]);
    }

    private function fiscalTabs(): array
    {
        return $this->activeTabs([
            ['route' => 'fiscal.entrada-nf.index', 'icon' => 'bi-receipt-cutoff', 'label' => 'Entrada de NF', 'patterns' => ['fiscal.entrada-nf.*']],
            ['route' => 'fiscal.notas.index', 'icon' => 'bi-file-earmark-text', 'label' => 'Notas Fiscais', 'patterns' => ['fiscal.notas.*']],
            ['route' => 'fiscal.index', 'icon' => 'bi-clipboard-check', 'label' => 'Fiscal', 'patterns' => ['fiscal.index', 'fiscal.consolidado.*']],
            ['route' => 'fiscal.documentos.index', 'icon' => 'bi-folder2-open', 'label' => 'Documentos', 'patterns' => ['fiscal.documentos.*']],
            ['route' => 'fiscal.produtores.index', 'icon' => 'bi-person-vcard', 'label' => 'Produtores', 'patterns' => ['fiscal.produtores.*']],
            ['route' => 'fiscal.certificados.index', 'icon' => 'bi-shield-lock', 'label' => 'Certificados', 'patterns' => ['fiscal.certificados.*']],
        ]);
    }

    private function activeTabs(array $tabs): array
    {
        return array_map(function (array $tab) {
            $tab['active'] = request()->routeIs(...$tab['patterns']);
            unset($tab['patterns']);

            return $tab;
        }, $tabs);
    }
}
