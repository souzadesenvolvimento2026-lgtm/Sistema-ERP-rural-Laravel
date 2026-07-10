@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Propriedade'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar propriedade</h1>
            <p class="subtitle">Atualize dados cadastrais, plano, cotação e georreferência da fazenda.</p>
        </div>
        <a class="btn" href="{{ route('propriedades.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('propriedades.update', $propriedade->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('propriedades.partials.dados-propriedade')

        <div class="actions">
            <a class="btn" href="{{ route('propriedades.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar propriedade</button>
        </div>
    </form>
@endsection
