@extends('layouts.farmfort', ['title' => 'FarmFort - Movimentações bancárias'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Movimentações bancárias</h1>
            <p class="subtitle">Extrato manual, conciliação e controle por conta.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.contas.index') }}">Contas bancárias</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Entradas</span><strong>R$ {{ number_format($totais['entradas'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Saídas</span><strong>R$ {{ number_format($totais['saidas'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Saldo líquido</span><strong>R$ {{ number_format($totais['saldo'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Pendentes</span><strong>{{ $totais['pendentes'] }}</strong></div>
    </section>

    @include('financeiro.movimentacoes.partials.form')
    @include('financeiro.movimentacoes.partials.tabela')
@endsection
