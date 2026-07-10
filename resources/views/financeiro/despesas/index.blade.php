@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Financeiro</a>
            <a class="btn primary" href="{{ route('financeiro.lancamentos.create', ['tipo' => 'despesa']) }}">+ Nova despesa</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('financeiro.despesas.partials.filtros')
    @include('financeiro.despesas.partials.tabela')
@endsection
