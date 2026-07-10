@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('usuarios.create') }}"><i class="bi bi-plus-lg"></i> Novo usuário</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('usuarios.partials.filtros')
    @include('usuarios.partials.tabela')
@endsection
