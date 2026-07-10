@extends('layouts.farmfort', ['title' => 'FarmFort - Duplicar Despesa'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Duplicar despesa</h1>
            <p class="subtitle">Use os dados da despesa original como base para criar um novo lançamento.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.despesas.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('financeiro.lancamentos.store') }}">
        @csrf

        @include('financeiro.lancamentos.partials.dados-principais')

        <div class="actions">
            <a class="btn" href="{{ route('financeiro.despesas.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Criar despesa duplicada</button>
        </div>
    </form>
@endsection
