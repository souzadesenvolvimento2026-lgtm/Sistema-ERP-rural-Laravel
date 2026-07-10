@extends('layouts.farmfort', ['title' => 'FarmFort - Novo Lançamento'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Novo lançamento</h1>
            <p class="subtitle">Cadastro financeiro migrado para Laravel, gravando nas tabelas atuais.</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('financeiro.lancamentos.store') }}">
        @csrf

        @include('financeiro.lancamentos.partials.dados-principais')

        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'financeiro']) }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar lançamento</button>
        </div>
    </form>
@endsection
