@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('patrimonio.index') }}">Patrimônios</a>
            <a class="btn" href="{{ route('patrimonio.edit', $patrimonio->id) }}">Editar</a>
            <a class="btn primary" href="{{ route('patrimonio.create') }}">+ Novo patrimônio</a>
        </div>
    </div>

    @include('partials.stats', ['cards' => $cards])
    @include('patrimonio.partials.resumo')
    @include('patrimonio.partials.form-lancamento')
    @include('patrimonio.partials.lancamentos')
@endsection
