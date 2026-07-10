@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Projeção'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar projeção</h1>
            <p class="subtitle">Atualização da previsão financeira do orçamento.</p>
        </div>
        <a class="btn" href="{{ route('orcamento.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('orcamento.update', $projecao->id) }}">
        @csrf
        @method('PUT')

        @include('orcamento.partials.dados-projecao')

        <div class="actions">
            <a class="btn" href="{{ route('orcamento.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Atualizar projeção</button>
        </div>
    </form>
@endsection
