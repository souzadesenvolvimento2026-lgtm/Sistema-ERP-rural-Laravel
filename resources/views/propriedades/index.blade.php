@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('propriedades.grupos.index') }}">Grupos</a>
            <a class="btn primary" href="{{ route('propriedades.create') }}">+ Nova propriedade</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('propriedades.partials.filtros')
    @include('propriedades.partials.tabela')
@endsection
