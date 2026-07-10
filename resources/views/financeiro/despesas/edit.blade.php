@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Despesa'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar despesa</h1>
            <p class="subtitle">Atualize fornecedor, categoria, safra, vencimento e valores da despesa.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.despesas.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('financeiro.despesas.update', $despesa) }}">
        @csrf
        @method('PUT')

        @include('financeiro.lancamentos.partials.dados-principais')

        <div class="actions">
            <a class="btn" href="{{ route('financeiro.despesas.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar despesa</button>
        </div>
    </form>
@endsection
