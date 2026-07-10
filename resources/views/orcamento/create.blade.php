@extends('layouts.farmfort', ['title' => 'FarmFort - Nova Projeção'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Nova projeção</h1>
            <p class="subtitle">Cadastro de previsão financeira para orçamento e comparativos.</p>
        </div>
        <a class="btn" href="{{ route('orcamento.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('orcamento.store') }}">
        @csrf

        @include('orcamento.partials.dados-projecao')

        <div class="actions">
            <a class="btn" href="{{ route('orcamento.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar projeção</button>
        </div>
    </form>
@endsection
