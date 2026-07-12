<nav class="ff-finance-tabs mb-4" aria-label="Submenus do financeiro">
    @foreach ($financeTabs as $tab)
        <a class="{{ $tab['active'] ? 'active' : '' }}" href="{{ route($tab['route']) }}">
            <i class="bi {{ $tab['icon'] }}"></i>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
    <a class="ff-finance-bank-shortcut {{ request()->routeIs('financeiro.contas.*') ? 'active' : '' }}" href="{{ route('financeiro.contas.index') }}" title="Bancos" aria-label="Bancos">
        <i class="bi bi-bank"></i>
        <span>Bancos</span>
    </a>
</nav>
