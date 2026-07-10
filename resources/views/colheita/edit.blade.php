@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Colheita'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar colheita</h1>
            <p class="subtitle">Ajuste uma carga de colheita ja lancada.</p>
        </div>
        <a class="btn" href="{{ route('colheita.index', ['safra_id' => $carga->safra_id, 'talhao_id' => $carga->talhao_id]) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('colheita.update', $carga->id) }}">
        @csrf
        @method('PUT')

        @include('colheita.partials.dados-carga', ['carga' => $carga])

        <div class="actions">
            <a class="btn" href="{{ route('colheita.index', ['safra_id' => $carga->safra_id, 'talhao_id' => $carga->talhao_id]) }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar alteracoes</button>
        </div>
    </form>
@endsection
