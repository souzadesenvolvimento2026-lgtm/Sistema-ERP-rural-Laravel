@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Financeiro</a>
            <a class="btn primary" href="{{ route('financeiro.lancamentos.create', ['tipo' => 'receita']) }}">+ Nova receita</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('financeiro.receitas.partials.filtros')
    @include('financeiro.receitas.partials.compradores')
    @include('financeiro.receitas.partials.tabela')
@endsection
