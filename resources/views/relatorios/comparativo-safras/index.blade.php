@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('relatorios.index') }}">Relatorios</a>
            <a class="btn" href="{{ route('relatorios.comparativo-safras.exportar', array_merge(request()->query(), ['formato' => 'csv'])) }}">CSV</a>
            <a class="btn" href="{{ route('relatorios.comparativo-safras.exportar', array_merge(request()->query(), ['formato' => 'excel'])) }}">Excel</a>
            <a class="btn" href="{{ route('relatorios.comparativo-safras.exportar', array_merge(request()->query(), ['formato' => 'pdf'])) }}">PDF</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('relatorios.comparativo-safras.partials.filtros')
    @include('relatorios.comparativo-safras.partials.avisos')
    @include('relatorios.comparativo-safras.partials.tabela')
@endsection
