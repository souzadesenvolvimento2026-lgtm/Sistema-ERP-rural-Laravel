@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        @include('modules.partials.actions')
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('modules.partials.records')
@endsection
