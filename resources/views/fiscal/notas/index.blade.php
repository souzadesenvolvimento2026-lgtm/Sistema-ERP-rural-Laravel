@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('modules.show', ['module' => 'fiscal']) }}">Fiscal</a>
            <a class="btn primary" href="{{ route('fiscal.notas.create') }}">Importar NF-e</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('fiscal.notas.partials.filtros')
    @include('fiscal.notas.partials.tabela')
@endsection
