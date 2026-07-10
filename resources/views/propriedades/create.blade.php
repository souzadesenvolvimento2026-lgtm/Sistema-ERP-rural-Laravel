@extends('layouts.farmfort', ['title' => 'FarmFort - Nova Propriedade'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Nova propriedade</h1>
            <p class="subtitle">Cadastro de fazenda migrado para Laravel.</p>
        </div>
        <a class="btn" href="{{ route('propriedades.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('propriedades.store') }}" enctype="multipart/form-data">
        @csrf

        @include('propriedades.partials.dados-propriedade')

        <div class="actions">
            <a class="btn" href="{{ route('propriedades.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar propriedade</button>
        </div>
    </form>
@endsection
