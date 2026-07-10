@extends('layouts.farmfort', ['title' => 'FarmFort - Nova Safra'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Nova safra</h1>
            <p class="subtitle">Cadastro de safra com vínculo aos talhões.</p>
        </div>
        <a class="btn" href="{{ route('safras.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('safras.store') }}">
        @csrf

        @include('safras.partials.dados-safra', ['safra' => null])

        <div class="actions">
            <a class="btn" href="{{ route('safras.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar safra</button>
        </div>
    </form>
@endsection
