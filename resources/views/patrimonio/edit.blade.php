@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Patrimônio'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar patrimônio</h1>
            <p class="subtitle">Atualize dados cadastrais, medidores e informações de aquisição.</p>
        </div>
        <a class="btn" href="{{ route('patrimonio.show', $patrimonio->id) }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('patrimonio.update', $patrimonio->id) }}" enctype="multipart/form-data">
        @csrf
        @method('put')

        @include('patrimonio.partials.dados-patrimonio')

        <div class="actions">
            <a class="btn" href="{{ route('patrimonio.show', $patrimonio->id) }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar alterações</button>
        </div>
    </form>
@endsection
