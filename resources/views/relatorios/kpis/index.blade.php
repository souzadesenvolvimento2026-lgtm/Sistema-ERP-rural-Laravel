@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn" href="{{ route('relatorios.index') }}">Relatorios</a>
    </div>

    @include('relatorios.kpis.partials.filtro')
    @include('relatorios.kpis.partials.resumo-safra')
    @include('relatorios.kpis.partials.indicadores')
    @include('relatorios.kpis.partials.comparativo')
@endsection
