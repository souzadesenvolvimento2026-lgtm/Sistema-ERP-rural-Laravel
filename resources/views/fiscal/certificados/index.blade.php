@extends('layouts.farmfort', ['title' => 'FarmFort - Certificados digitais'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Certificados digitais</h1>
            <p class="subtitle">Certificados A1/A3 usados nas rotinas fiscais eletrônicas da propriedade.</p>
        </div>
        <a class="btn" href="{{ route('fiscal.index') }}">Fiscal</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Certificados</span><strong>{{ $totais['certificados'] }}</strong></div>
        <div class="stat"><span>Ativos</span><strong>{{ $totais['ativos'] }}</strong></div>
        <div class="stat"><span>Vencidos</span><strong>{{ $totais['vencidos'] }}</strong></div>
        <div class="stat"><span>Vencendo</span><strong>{{ $totais['vencendo'] }}</strong></div>
    </section>

    @if ($totais['vencendo'] > 0)
        <section class="panel alert-panel">
            <strong>Atenção: certificado digital próximo do vencimento.</strong>
            <p class="muted">Revise os certificados ativos para evitar bloqueio das rotinas fiscais.</p>
        </section>
    @endif

    @include('fiscal.certificados.partials.form')
    @include('fiscal.certificados.partials.tabela')
@endsection
