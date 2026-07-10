@extends('layouts.farmfort', ['title' => 'FarmFort - Produtores'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Produtores</h1>
            <p class="subtitle">Cadastro fiscal usado nos lançamentos financeiros e notas da propriedade.</p>
        </div>
        <a class="btn" href="{{ route('fiscal.index') }}">Fiscal</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Produtores</span><strong>{{ $totais['produtores'] }}</strong></div>
        <div class="stat"><span>Ativos</span><strong>{{ $totais['ativos'] }}</strong></div>
        <div class="stat"><span>Inativos</span><strong>{{ $totais['inativos'] }}</strong></div>
        <div class="stat"><span>Participação ativa</span><strong>{{ number_format($totais['participacao'], 2, ',', '.') }}%</strong></div>
    </section>

    @include('fiscal.produtores.partials.form')
    @include('fiscal.produtores.partials.tabela')
@endsection
