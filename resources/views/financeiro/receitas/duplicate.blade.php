@extends('layouts.farmfort', ['title' => 'FarmFort - Duplicar Receita'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Duplicar receita</h1>
            <p class="subtitle">Use os dados da receita original como base para criar um novo lançamento.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.receitas.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('financeiro.lancamentos.store') }}">
        @csrf

        @include('financeiro.lancamentos.partials.dados-principais')

        <div class="actions">
            <a class="btn" href="{{ route('financeiro.receitas.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Criar receita duplicada</button>
        </div>
    </form>
@endsection
