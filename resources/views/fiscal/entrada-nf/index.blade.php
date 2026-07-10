@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('fiscal.index') }}">Fiscal</a>
            <a class="btn primary" href="{{ route('fiscal.entrada-nf.create') }}">+ Nova entrada</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('fiscal.entrada-nf.partials.filtros')
    @include('fiscal.entrada-nf.partials.tabela')
@endsection
