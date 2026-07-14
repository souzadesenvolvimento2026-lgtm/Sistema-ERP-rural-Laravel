@extends('layouts.farmfort', [
    'title' => 'FarmFort - Mapa dos Talhões',
    'bodyClass' => 'ff-map-fixed-body',
])

@php
    use App\Support\FarmFormat;
    use Illuminate\Support\Js;

    $cards = $mapCards ?? [];
    $formatArea = fn ($value) => number_format((float)$value, 2, ',', '.');
    $propertyTitle = strtoupper((string)($propertyName ?? 'Fazenda'));
@endphp

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">

    <div class="ff-map-page">
        <div class="ff-map-toolbar">
            <div>
                <span class="ff-map-eyebrow">Visualização por satélite</span>
                <h1>{{ $propertyTitle }}</h1>
            </div>
            <div class="ff-map-actions">
                <button class="btn primary" type="button" id="btnDrawTalhaoTop">
                    <i class="bi bi-pencil-square"></i> Desenhar polígono
                </button>
                <button class="btn btn-info-outline" type="button" id="btnDrawPivoTop">
                    <i class="bi bi-record-circle"></i> Pivô
                </button>
                <a class="btn btn-success-outline" href="{{ route('talhoes.index') }}">
                    <i class="bi bi-upload"></i> Importar KML/KMZ/SHP
                </a>
                <a class="btn btn-warning" href="{{ route('talhoes.index') }}">
                    <i class="bi bi-sliders"></i> Gerenciar talhões
                </a>
                <a class="btn" href="{{ route('talhoes.exportar-kml') }}">
                    <i class="bi bi-download"></i> Exportar talhões
                    <i class="bi bi-chevron-down ms-1"></i>
                </a>
            </div>
        </div>

        <div class="ff-map-kpis">
            <article class="ff-map-kpi">
                <span>Talhões no mapa</span>
                <strong>{{ $cards['talhoes'] ?? $talhoes->count() }}</strong>
                <small>{{ $cards['talhoes'] ?? $talhoes->count() }} cadastrados</small>
            </article>
            <article class="ff-map-kpi">
                <span>Área total</span>
                <strong>{{ $formatArea($cards['area_total'] ?? 0) }} ha</strong>
                <small>Somatório dos talhões</small>
            </article>
            <article class="ff-map-kpi">
                <span>Total gasto</span>
                <strong>{{ FarmFormat::money($cards['total_gasto'] ?? 0) }}</strong>
                <small>Despesas vinculadas</small>
            </article>
            <article class="ff-map-kpi">
                <span>Região</span>
                <strong>{{ $cards['regiao'] ?? 'Região da fazenda' }}</strong>
                <small>{{ $cards['coordenadas'] ?? '-' }}</small>
            </article>
        </div>

        <section class="farm-map-shell ff-map-print-shell">
            <aside class="farm-map-sidebar ff-map-list-panel">
                <div class="farm-map-sidebar-head">
                    <strong><i class="bi bi-grid-3x3-gap"></i> Talhões</strong>
                    <span class="ff-map-count">{{ $cards['talhoes_geo'] ?? $talhoes->count() }} geo</span>
                </div>
                <div class="map-list">
                    @forelse ($talhoes as $talhao)
                        @php
                            $coords = $talhao['lat'] && $talhao['lng']
                                ? number_format((float)$talhao['lat'], 6, '.', '').', '.number_format((float)$talhao['lng'], 6, '.', '')
                                : 'Sem coordenadas';
                        @endphp
                        <div class="map-list-item" data-map-list-talhao="{{ $talhao['id'] }}">
                            <button class="map-list-focus" type="button" data-map-focus-talhao="{{ $talhao['id'] }}" @disabled(empty($talhao['points']) && (!$talhao['lat'] || !$talhao['lng']))>
                                <span class="map-list-title">{{ $talhao['nome'] }}</span>
                                <span class="map-list-meta">Polígono - {{ $formatArea($talhao['area'] ?? 0) }} ha - {{ $talhao['custo_formatado'] ?? FarmFormat::money(0) }}</span>
                                <span class="map-list-meta">{{ $coords }}</span>
                            </button>
                            <button class="map-list-edit" type="button" data-map-edit-talhao="{{ $talhao['id'] }}" title="Editar dados do talhão" aria-label="Editar dados do talhão {{ $talhao['nome'] }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </div>
                    @empty
                        <div class="empty-state">
                            <strong>Nenhum talhão no mapa</strong>
                            <span>Use Desenhar polígono para cadastrar o primeiro.</span>
                        </div>
                    @endforelse
                </div>
            </aside>

            <div class="farm-map-panel ff-map-panel">
                <div id="talhaoMap" class="farm-map ff-map-canvas"></div>
            </div>
        </section>

        <div class="ff-map-hidden-forms">
            @include('talhoes.partials.mapa-form')
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .ff-talhao-edit-dialog { width: min(94vw, 520px); }
        .ff-talhao-edit-content {
            overflow: hidden;
            border: 1px solid var(--ff-border);
            border-radius: 8px;
            background: var(--ff-surface);
            color: var(--ff-text);
            box-shadow: 0 22px 70px rgba(3, 10, 18, .34);
        }
        .ff-talhao-edit-modal .modal-header {
            min-height: 64px;
            border-bottom: 0;
            background: #00754f !important;
            color: #fff !important;
        }
        .ff-talhao-edit-modal .modal-body,
        .ff-talhao-edit-modal .modal-footer {
            background: var(--ff-surface) !important;
            color: var(--ff-text) !important;
        }
        .ff-talhao-edit-modal .modal-body { padding: 18px; }
        .ff-talhao-edit-modal .modal-footer { border-top: 1px solid var(--ff-border); }
        .ff-talhao-edit-modal label { display: grid; gap: 7px; color: var(--ff-text) !important; }
        .ff-talhao-edit-modal input,
        .ff-talhao-edit-modal select,
        .ff-talhao-edit-modal textarea {
            border-color: var(--ff-border-strong) !important;
            background: var(--ff-surface-2) !important;
            color: var(--ff-text) !important;
        }
        .ff-talhao-edit-modal input[readonly] {
            color: var(--ff-muted) !important;
            background: var(--ff-surface-3) !important;
        }
        .ff-talhao-edit-tools { display: grid; gap: 8px; margin-top: 16px; }
        .ff-talhao-edit-tools strong { color: var(--ff-text); font-size: 13px; font-weight: 900; }
        .ff-talhao-edit-tools > div { display: flex; flex-wrap: wrap; gap: 7px; }
        .ff-talhao-edit-tools .btn { min-height: 34px; padding: 7px 10px; font-size: 13px; }
        .ff-talhao-edit-tools .btn:disabled { opacity: .5; cursor: not-allowed; }
        .ff-talhao-edit-tools small { color: var(--ff-muted); font-size: 12px; }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script src="{{ asset('js/talhao-mapa-modal.js') }}?v={{ @filemtime(public_path('js/talhao-mapa-modal.js')) }}"></script>
    <script>
        window.initTalhaoMapa({
            talhoes: {{ Js::from($talhoes) }},
            centro: [{{ $centro['lat'] ?? -15.7801 }}, {{ $centro['lng'] ?? -47.9292 }}],
        });
    </script>
@endpush
