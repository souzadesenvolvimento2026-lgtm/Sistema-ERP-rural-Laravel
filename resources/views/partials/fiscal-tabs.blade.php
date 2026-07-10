@php
    $tabs = [
        ['route' => 'fiscal.entrada-nf.index', 'icon' => 'bi-receipt-cutoff', 'label' => 'Entrada de NF', 'active' => ['fiscal.entrada-nf.*']],
        ['route' => 'fiscal.notas.index', 'icon' => 'bi-file-earmark-text', 'label' => 'Notas Fiscais', 'active' => ['fiscal.notas.*']],
        ['route' => 'fiscal.index', 'icon' => 'bi-clipboard-check', 'label' => 'Fiscal', 'active' => ['fiscal.index', 'fiscal.consolidado.*']],
        ['route' => 'fiscal.documentos.index', 'icon' => 'bi-folder2-open', 'label' => 'Documentos', 'active' => ['fiscal.documentos.*']],
        ['route' => 'fiscal.produtores.index', 'icon' => 'bi-person-vcard', 'label' => 'Produtores', 'active' => ['fiscal.produtores.*']],
        ['route' => 'fiscal.certificados.index', 'icon' => 'bi-shield-lock', 'label' => 'Certificados', 'active' => ['fiscal.certificados.*']],
    ];
@endphp

<div class="ff-fiscal-shell mb-4">
    <ul class="nav ff-fiscal-tabs">
        @foreach ($tabs as $tab)
            @php $isActive = collect($tab['active'])->contains(fn ($pattern) => request()->routeIs($pattern)); @endphp
            <li class="nav-item">
                <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ route($tab['route']) }}">
                    <i class="bi {{ $tab['icon'] }}"></i>
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
