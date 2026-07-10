@php
    $tabs = [
        ['route' => 'financeiro.index', 'icon' => 'bi-plus-circle', 'label' => 'Lançamentos', 'active' => ['financeiro.index', 'financeiro.despesas.*', 'financeiro.receitas.*', 'financeiro.lancamentos.*']],
        ['route' => 'relatorios.fluxo-caixa', 'icon' => 'bi-graph-up-arrow', 'label' => 'Fluxo de Caixa', 'active' => ['relatorios.fluxo-caixa']],
        ['route' => 'relatorios.dre', 'icon' => 'bi-bar-chart-line', 'label' => 'DRE', 'active' => ['relatorios.dre']],
        ['route' => 'relatorios.orcado-realizado', 'icon' => 'bi-table', 'label' => 'Orçado x Realizado', 'active' => ['relatorios.orcado-realizado']],
        ['route' => 'financeiro.analise-despesas.index', 'icon' => 'bi-pie-chart', 'label' => 'DRE Agrícola', 'active' => ['financeiro.analise-despesas.*']],
        ['route' => 'relatorios.comparativo-safras.index', 'icon' => 'bi-columns-gap', 'label' => 'Comparativo de Safras', 'active' => ['relatorios.comparativo-safras.*']],
    ];
@endphp

<nav class="ff-finance-tabs mb-4" aria-label="Submenus do financeiro">
    @foreach ($tabs as $tab)
        @php $isActive = collect($tab['active'])->contains(fn ($pattern) => request()->routeIs($pattern)); @endphp
        <a class="{{ $isActive ? 'active' : '' }}" href="{{ route($tab['route']) }}">
            <i class="bi {{ $tab['icon'] }}"></i>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
    <a class="ff-finance-bank-shortcut {{ request()->routeIs('financeiro.contas.*') ? 'active' : '' }}" href="{{ route('financeiro.contas.index') }}" title="Bancos" aria-label="Bancos">
        <i class="bi bi-bank"></i>
        <span>Bancos</span>
    </a>
</nav>
