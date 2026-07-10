@extends('layouts.farmfort', ['title' => 'FarmFort - Editar conta'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar conta bancária</h1>
            <p class="subtitle">Atualize os dados da conta usada nos lançamentos e movimentações.</p>
        </div>
        <a class="btn" href="{{ route('financeiro.contas.index') }}">Voltar</a>
    </div>

    @include('financeiro.contas.partials.form')
    @include('financeiro.contas.partials.tabela')
@endsection
