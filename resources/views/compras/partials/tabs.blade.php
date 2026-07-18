@php
    $activeCompraTab = $activeCompraTab ?? 'pedidos';
@endphp

<nav class="ff-purchase-tabs" aria-label="Navegação do módulo Compras">
    <a
        @class(['ff-purchase-tab', 'active' => $activeCompraTab === 'pedidos'])
        href="{{ route('compras.pedidos.index') }}"
    >
        <i class="bi bi-clipboard-check"></i>
        Pedidos Fiscais
    </a>
    <a
        @class(['ff-purchase-tab', 'active' => $activeCompraTab === 'fornecedores'])
        href="{{ route('compras.fornecedores.index') }}"
    >
        <i class="bi bi-building"></i>
        Fornecedores
    </a>
</nav>
