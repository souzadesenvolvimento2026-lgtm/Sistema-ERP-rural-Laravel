@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Produto'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar produto</h1>
            <p class="subtitle">Atualize o cadastro, dados fiscais e classificação do produto.</p>
        </div>
        <a class="btn" href="{{ route('produtos.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('produtos.update', $produto->id) }}">
        @csrf
        @method('PUT')

        @include('produtos.partials.dados-produto')

        <div class="actions">
            <a class="btn" href="{{ route('produtos.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar produto</button>
        </div>
    </form>
@endsection
