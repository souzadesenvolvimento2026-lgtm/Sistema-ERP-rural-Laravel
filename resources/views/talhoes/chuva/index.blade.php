@extends('layouts.farmfort', ['title' => 'FarmFort - Chuva'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Chuva</h1>
            <p class="subtitle">Registros de precipitação por ano, talhão e fonte.</p>
        </div>
        <a class="btn" href="{{ route('talhoes.index') }}">Talhões</a>
    </div>

    @include('talhoes.chuva.partials.filtro')

    <section class="stats">
        <div class="stat"><span>Chuva total</span><strong>{{ number_format($totais['total'], 1, ',', '.') }} mm</strong></div>
        <div class="stat"><span>Dias com registro</span><strong>{{ $totais['dias'] }}</strong></div>
        <div class="stat"><span>Média por dia</span><strong>{{ number_format($totais['media'], 1, ',', '.') }} mm</strong></div>
        <div class="stat"><span>Maior evento</span><strong>{{ number_format($totais['maior'], 1, ',', '.') }} mm</strong></div>
    </section>

    @include('talhoes.chuva.partials.form')
    @include('talhoes.chuva.partials.tabela')
    @include('talhoes.chuva.partials.mensal')
@endsection
