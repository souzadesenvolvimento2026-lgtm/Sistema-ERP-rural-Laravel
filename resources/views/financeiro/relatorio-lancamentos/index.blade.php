@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Financeiro</a>
            <a class="btn" href="{{ route('financeiro.relatorio-lancamentos.exportar', array_merge(request()->query(), ['formato' => 'csv'])) }}">CSV</a>
            <a class="btn" href="{{ route('financeiro.relatorio-lancamentos.exportar', array_merge(request()->query(), ['formato' => 'pdf'])) }}">PDF</a>
            <a class="btn primary" href="{{ route('financeiro.relatorio-lancamentos.exportar', array_merge(request()->query(), ['formato' => 'excel'])) }}">Excel</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('financeiro.relatorio-lancamentos.partials.filtros')
    @include('financeiro.relatorio-lancamentos.partials.tabela')
@endsection
