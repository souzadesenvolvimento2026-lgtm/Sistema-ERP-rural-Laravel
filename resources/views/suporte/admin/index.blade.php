@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn" href="{{ route('dashboard') }}">Dashboard</a>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('suporte.admin.partials.conversas')
    @include('suporte.admin.partials.atendentes')
    @include('suporte.admin.partials.ultimas-respostas')
@endsection
