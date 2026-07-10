@extends('layouts.farmfort', ['title' => 'FarmFort - Novo Talhão'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Novo talhão</h1>
            <p class="subtitle">Cadastro de talhão migrado para Laravel.</p>
        </div>
        <a class="btn" href="{{ route('talhoes.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('talhoes.store') }}">
        @csrf

        @include('talhoes.partials.dados-talhao', ['talhao' => null])

        <div class="actions">
            <a class="btn" href="{{ route('talhoes.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar talhão</button>
        </div>
    </form>
@endsection
