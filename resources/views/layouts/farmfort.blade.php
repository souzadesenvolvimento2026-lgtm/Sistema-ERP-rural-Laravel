<!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FarmFort - {{ $displayTitle }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('assets/img/farmfort-favicon.png') }}" type="image/png" data-farmfort-favicon="primary">
    <link rel="shortcut icon" href="{{ asset('assets/img/farmfort-favicon.png') }}" type="image/png">
    <script>
        (function () {
            var theme = localStorage.getItem('farmflow-theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/farmfort.css') }}?v={{ @filemtime(public_path('css/farmfort.css')) }}">
    @stack('styles')
</head>
@php
    $bodyClass = trim((string) ($bodyClass ?? ''));
@endphp
<body @if ($bodyClass !== '') class="{{ $bodyClass }}" @endif>
@unless ($fullWidth)
<aside class="module-rail sidebar" aria-label="Menus principais">
    <a href="{{ route('dashboard') }}" class="module-brand" aria-label="Ir para a tela inicial do FarmFort">
        <img src="{{ asset('assets/img/farmfort-mark.png') }}" alt="" width="40" height="40">
        <span>
            <strong>FarmFort</strong>
            <small>ERP Rural</small>
        </span>
    </a>

    @if ($isSystemAdmin)
        <div class="ff-admin-rail-label">
            <strong>Sistema FarmFort</strong>
            <span>Painel admin</span>
        </div>

        @foreach ($adminMenu as $item)
            <div class="module-rail-item {{ $active === $item['key'] ? 'active' : '' }}">
                <a href="{{ $item['route'] }}" class="module-rail-button" title="{{ $item['label'] }}">
                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            </div>
        @endforeach

        <div class="ff-admin-rail-label ff-admin-property-label">
            <strong>Propriedade selecionada</strong>
            <span>{{ $propertyName }}</span>
        </div>
    @endif

    @foreach ($menu as $item)
        <div class="module-rail-item {{ $active === $item['key'] ? 'active' : '' }}">
            <a href="{{ $item['route'] }}" class="module-rail-button" title="{{ $item['label'] }}">
                @if (!empty($item['icon_svg']))
                    <span class="ff-menu-svg-icon" aria-hidden="true" style="--ff-icon-url:url('{{ asset($item['icon_svg']) }}')"></span>
                @else
                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                @endif
                <span>{{ $item['label'] }}</span>
            </a>
        </div>
    @endforeach

    <div class="module-rail-credit" title="Desenvolvido por Souza Development">
        <span>Desenvolvido por</span>
        <strong>Souza Development</strong>
    </div>
</aside>
@endunless

<div class="main {{ $fullWidth ? 'main-full' : '' }}">
    <div class="topbar">
        <div class="topbar-main">
            <h6 class="topbar-title">
                <i class="bi bi-chevron-right me-1 text-muted topbar-chevron"></i>
                {{ $topbarLabel }}
            </h6>
        </div>

        <div class="topbar-actions">
            <div class="topbar-context">
                <form>
                    <label>Propriedade</label>
                    <select aria-label="Propriedade atual">
                        <option selected>{{ $propertyName }}</option>
                    </select>
                </form>
            </div>

            @isset($safraName)
                <div class="topbar-context">
                    <form>
                        <label>Safra</label>
                        <select aria-label="Safra atual">
                            <option selected>{{ $safraName }}</option>
                        </select>
                    </form>
                </div>
                <span class="topbar-safra-pill">
                    <i class="bi bi-card-checklist"></i>{{ $safraName }}
                </span>
            @endisset

            <button type="button" class="theme-toggle" id="themeToggle" aria-label="Alternar tema" title="Alternar tema">
                <i class="bi bi-moon-stars"></i>
            </button>

            @if ($isSystemAdmin)
                <button type="button" class="ff-unlock-button" data-bs-toggle="modal" data-bs-target="#systemUnlockModal">
                    <i class="bi bi-lock-fill"></i>
                    <span>Liberar edição</span>
                </button>
            @endif

            <span class="topbar-user">
                <i class="bi bi-person-circle me-1"></i>{{ $userName }}
                @if ($profile !== '')
                    <span class="pill topbar-profile ms-1">{{ \App\Support\FarmFormat::statusLabel($profile) }}</span>
                @endif
            </span>

            <form method="post" action="{{ route('logout') }}" class="topbar-logout-form">
                @csrf
                <button type="submit" class="topbar-logout" title="Sair do sistema">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Sair</span>
                </button>
            </form>
        </div>
    </div>

    <div class="page-body">
        @if (session('success'))
            <div class="farm-toast farm-toast-success">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="farm-toast farm-toast-danger">
                <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
            </div>
        @endif

        @if ($isFinanceSection)
            @include('partials.financeiro-tabs')
        @elseif (($active ?? '') === 'fiscal')
            @include('partials.fiscal-tabs')
        @endif

        @yield('content')
    </div>
</div>

@if ($isSystemAdmin)
    <div class="modal fade" id="systemUnlockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="{{ route('system.unlock.store') }}" class="modal-content ff-modal-content">
                @csrf
                <input type="hidden" name="return_to" value="{{ url()->current() }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lock-fill me-2"></i>Liberar edição</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <label class="field">
                        <span>Senha do administrador</span>
                        <input type="password" name="senha_confirmacao" required autocomplete="current-password">
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn primary">Liberar edição</button>
                </div>
            </form>
        </div>
    </div>
@endif

@unless ($fullWidth || request()->routeIs('suporte.admin.index'))
    @include('partials.support-widget')
@endunless

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('js/farmfort.js') }}?v={{ @filemtime(public_path('js/farmfort.js')) }}"></script>
@stack('scripts')
</body>
</html>
