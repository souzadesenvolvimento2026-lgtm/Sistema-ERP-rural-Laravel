@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Safra'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar safra</h1>
            <p class="subtitle">Atualize dados, status e talhões vinculados à safra.</p>
        </div>
        <a class="btn" href="{{ route('safras.index', ['status' => 'todas']) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('safras.update', $safra->id) }}">
        @csrf
        @method('PUT')

        @include('safras.partials.dados-safra')

        <div class="actions">
            <a class="btn" href="{{ route('safras.index', ['status' => 'todas']) }}">Cancelar</a>
            <button class="btn primary" type="submit">Atualizar safra</button>
        </div>
    </form>
@endsection
