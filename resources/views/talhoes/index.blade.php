@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@php
    $statusAtual = $filtros['status'] ?? 'ativos';
    $propertyTitle = strtoupper((string)($propertyName ?? 'Propriedade'));
@endphp

@section('content')
    <div class="ff-talhao-index">
        <section class="stats ff-talhao-stats" aria-label="Resumo dos talhões">
            @foreach ($cards as $card)
                <div class="stat">
                    <span>{{ $card['label'] }}</span>
                    <strong class="{{ $card['tone'] ?? '' }}">{{ $card['value'] }}</strong>
                    @if (!empty($card['hint']))
                        <small>{{ $card['hint'] }}</small>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="ff-talhao-unify-callout">
            <div>
                <strong><i class="bi bi-intersect"></i> Unificar / corrigir talhões</strong>
                <span>Use quando dois ou mais talhões foram cadastrados separados e precisam virar um só.</span>
            </div>
            <a class="btn btn-warning" href="#talhoesUnificacao"><i class="bi bi-intersect"></i> Abrir unificação</a>
        </section>

        <section class="panel ff-talhao-list-panel">
            <div class="panel-head">
                <h2><i class="bi bi-grid-3x3-gap"></i> Talhões - {{ $propertyTitle }} <span class="visually-hidden">Talhões da propriedade</span></h2>
                <div class="actions">
                    <a class="btn btn-outline-primary" href="{{ route('talhoes.mapa') }}"><i class="bi bi-globe-americas"></i> Visualizar mapa</a>
                    <a class="btn btn-success-outline" href="{{ route('talhoes.exportar-kml') }}"><i class="bi bi-download"></i> Gerar KML <span class="visually-hidden">Exportar KML</span></a>
                    <a class="btn btn-warning" href="#talhoesUnificacao"><i class="bi bi-intersect"></i> Unificar talhões</a>
                    <a class="btn primary" href="{{ route('talhoes.create') }}"><i class="bi bi-plus-lg"></i> Novo Talhão</a>
                </div>
            </div>

            <div class="ff-talhao-tabs">
                <a class="{{ $statusAtual === 'ativos' ? 'active' : '' }}" href="{{ route('talhoes.index', ['status' => 'ativos']) }}">
                    Ativos <span>{{ $counts['ativos'] ?? 0 }}</span>
                </a>
                <a class="{{ $statusAtual === 'desativados' ? 'active' : '' }}" href="{{ route('talhoes.index', ['status' => 'desativados']) }}">
                    Desativados <span>{{ $counts['desativados'] ?? 0 }}</span>
                </a>
            </div>

            <div class="ff-talhao-info">
                Talhões não são excluídos do sistema. Eles podem ser desativados somente quando não tiverem vínculo ou movimentação em safra ativa.
            </div>

            @include('talhoes.partials.tabela')
        </section>

        <details id="talhoesUnificacao" class="ff-talhao-drawer">
            <summary><i class="bi bi-intersect"></i> Abrir formulário de unificação</summary>
            @include('talhoes.partials.unificar')
        </details>

        <details class="ff-talhao-drawer">
            <summary><i class="bi bi-upload"></i> Importar arquivo geoespacial</summary>
            @include('talhoes.partials.importar-geo')
        </details>
    </div>
@endsection
