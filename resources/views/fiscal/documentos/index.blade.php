@extends('layouts.farmfort', ['title' => 'FarmFort - Documentos'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Documentos</h1>
            <p class="subtitle">Central de documentos fiscais, contratos, comprovantes e anexos da propriedade.</p>
        </div>
        <a class="btn" href="{{ route('fiscal.index') }}">Fiscal</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Documentos</span><strong>{{ $totais['documentos'] }}</strong></div>
        <div class="stat"><span>Pendentes</span><strong>{{ $totais['pendentes'] }}</strong></div>
        <div class="stat"><span>Valor vinculado</span><strong>R$ {{ number_format($totais['valor'], 2, ',', '.') }}</strong></div>
        <div class="stat"><span>Com arquivo</span><strong>{{ $totais['com_arquivo'] }}</strong></div>
    </section>

    @include('fiscal.documentos.partials.form')
    @include('fiscal.documentos.partials.tabela')
@endsection
