@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Financeiro</a>
    </div>

    @include('financeiro.analise-despesas.partials.filtros')
    @include('financeiro.analise-despesas.partials.contexto')
    @include('partials.stats', ['cards' => $cards])
    @include('financeiro.analise-despesas.partials.tabelas')
@endsection
