@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Talhão'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar talhão</h1>
            <p class="subtitle">Atualize área, localização, pivô e descrição do talhão.</p>
        </div>
        <a class="btn" href="{{ route('talhoes.index', ['status' => 'todos']) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('talhoes.update', $talhao->id) }}">
        @csrf
        @method('PUT')

        @include('talhoes.partials.dados-talhao')

        <div class="actions">
            <a class="btn" href="{{ route('talhoes.index', ['status' => 'todos']) }}">Cancelar</a>
            <button class="btn primary" type="submit">Atualizar talhão</button>
        </div>
    </form>
@endsection
