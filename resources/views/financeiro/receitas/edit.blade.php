@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Receita'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar receita</h1>
            <p class="subtitle">Atualize os dados financeiros, comprador, safra e quantidades da receita.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.receitas.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('financeiro.receitas.update', $receita) }}">
        @csrf
        @method('PUT')

        @include('financeiro.lancamentos.partials.dados-principais')

        <div class="actions">
            <a class="btn" href="{{ route('financeiro.receitas.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar receita</button>
        </div>
    </form>
@endsection
