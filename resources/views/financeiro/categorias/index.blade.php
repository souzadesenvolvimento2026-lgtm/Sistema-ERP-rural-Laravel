@extends('layouts.farmfort', ['title' => 'FarmFort - Categorias'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Categorias</h1>
            <p class="subtitle">Categorias e subcategorias usadas em financeiro, compras, produtos e orçamento.</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Financeiro</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Categorias</span><strong>{{ $totais['categorias'] }}</strong></div>
        <div class="stat"><span>Ativas</span><strong>{{ $totais['ativas'] }}</strong></div>
        <div class="stat"><span>Principais</span><strong>{{ $totais['principais'] }}</strong></div>
        <div class="stat"><span>Subcategorias</span><strong>{{ $totais['subcategorias'] }}</strong></div>
    </section>

    @include('financeiro.categorias.partials.form')
    @include('financeiro.categorias.partials.tabela')
@endsection
