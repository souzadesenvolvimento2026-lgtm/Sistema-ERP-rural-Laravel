@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn primary" href="{{ route('patrimonio.create') }}"><i class="bi bi-plus-lg"></i> Novo patrimônio</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('patrimonio.partials.filtros')
    @include('patrimonio.partials.tabela')
@endsection
