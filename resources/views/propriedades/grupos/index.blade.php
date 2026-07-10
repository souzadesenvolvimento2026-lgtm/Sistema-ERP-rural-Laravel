@extends('layouts.farmfort', ['title' => 'FarmFort - Grupos de fazendas'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Grupos de fazendas</h1>
            <p class="subtitle">Agrupe propriedades Premium para operação por equipe e aprovador.</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'propriedades']) }}">Propriedades</a>
    </div>

    <section class="stats">
        <div class="stat"><span>Grupos</span><strong>{{ $totais['grupos'] }}</strong></div>
        <div class="stat"><span>Fazendas vinculadas</span><strong>{{ $totais['fazendas'] }}</strong></div>
        <div class="stat"><span>Usuários vinculados</span><strong>{{ $totais['usuarios'] }}</strong></div>
        <div class="stat"><span>Premium ativas</span><strong>{{ $totais['premium'] }}</strong></div>
    </section>

    @include('propriedades.grupos.partials.form')
    @include('propriedades.grupos.partials.tabela')
@endsection
