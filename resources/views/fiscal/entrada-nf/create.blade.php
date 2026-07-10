@extends('layouts.farmfort', ['title' => 'FarmFort - Entrada de NF'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Entrada de NF</h1>
            <p class="subtitle">Lançamento manual de nota de entrada, item inicial e parcela financeira.</p>
        </div>
        <a class="btn" href="{{ route('modules.show', ['module' => 'fiscal']) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('fiscal.entrada-nf.store') }}">
        @csrf

        @include('fiscal.entrada-nf.partials.capa-nota')
        @include('fiscal.entrada-nf.partials.primeiro-item')

        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'fiscal']) }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar entrada</button>
        </div>
    </form>
@endsection
