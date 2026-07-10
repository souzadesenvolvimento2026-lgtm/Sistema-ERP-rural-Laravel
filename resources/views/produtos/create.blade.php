@extends('layouts.farmfort', ['title' => 'FarmFort - Novo Produto'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Novo produto</h1>
            <p class="subtitle">Cadastro do estoque de produtos migrado para Laravel.</p>
        </div>
        <a class="btn" href="{{ route('produtos.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('produtos.store') }}">
        @csrf

        @include('produtos.partials.dados-produto')

        <div class="actions">
            <a class="btn" href="{{ route('produtos.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar produto</button>
        </div>
    </form>
@endsection
