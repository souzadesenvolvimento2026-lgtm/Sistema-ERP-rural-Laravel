@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('colheita.create') }}"><i class="bi bi-plus-lg"></i> Nova colheita</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('colheita.partials.filtros')
    @include('colheita.partials.talhoes')
    @include('colheita.partials.tabela')
@endsection
