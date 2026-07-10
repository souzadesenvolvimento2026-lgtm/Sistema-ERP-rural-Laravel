@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn primary" href="{{ route('fiscal.entrada-nf.create') }}"><i class="bi bi-plus-lg"></i> Nova entrada</a>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('fiscal.consolidado.partials.filtros')
    @include('fiscal.consolidado.partials.tabela')
@endsection
