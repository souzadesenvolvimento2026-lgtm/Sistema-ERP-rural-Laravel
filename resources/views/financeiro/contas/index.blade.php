@extends('layouts.farmfort', ['title' => 'FarmFort - Contas bancárias'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Contas bancárias</h1>
            <p class="subtitle">Cadastro das contas usadas nos lançamentos e conciliação financeira.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.movimentacoes.index') }}">Movimentações</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Contas</span><strong>{{ $totais['contas'] }}</strong></div>
        <div class="stat"><span>Ativas</span><strong>{{ $totais['ativas'] }}</strong></div>
        <div class="stat"><span>Saldo inicial</span><strong>R$ {{ number_format($totais['saldo_inicial'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Saldo atual</span><strong>R$ {{ number_format($totais['saldo_atual'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Inativas</span><strong>{{ $totais['inativas'] }}</strong></div>
    </section>

    @include('financeiro.contas.partials.form')
    @include('financeiro.contas.partials.transferencia')
    @include('financeiro.contas.partials.transferencias')
    @include('financeiro.contas.partials.tabela')
@endsection
