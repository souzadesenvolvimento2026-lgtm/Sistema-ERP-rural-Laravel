@extends('layouts.farmfort', ['title' => 'FarmFort - Nova Colheita'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Nova colheita</h1>
            <p class="subtitle">Entrada manual de colheita migrada para Laravel.</p>
        </div>
        <a class="btn" href="{{ route('colheita.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('colheita.store') }}">
        @csrf

        @include('colheita.partials.dados-carga')

        <div class="actions">
            <a class="btn" href="{{ route('colheita.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar colheita</button>
        </div>
    </form>
@endsection
