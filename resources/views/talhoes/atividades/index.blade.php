@extends('layouts.farmfort', ['title' => 'FarmFort - Atividades de Campo'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Atividades de Campo</h1>
            <p class="subtitle">Planejamento e execução das operações por safra e talhão.</p>
        </div>
        <a class="btn" href="{{ route('talhoes.index') }}">Talhões</a>
    </div>

    @include('talhoes.atividades.partials.filtro')

    <section class="stats">
        <div class="stat"><span>Atividades</span><strong>{{ $totais['atividades'] }}</strong></div>
        <div class="stat"><span>Concluídas</span><strong>{{ $totais['concluidas'] }}</strong></div>
        <div class="stat"><span>Pendentes</span><strong>{{ $totais['pendentes'] }}</strong></div>
        <div class="stat"><span>Custo estimado</span><strong>R$ {{ number_format($totais['custo'], 2, ',', '.') }}</strong></div>
    </section>

    @include('talhoes.atividades.partials.tipo-resumo')
    @include('talhoes.atividades.partials.form')
    @include('talhoes.atividades.partials.tabela')
@endsection
