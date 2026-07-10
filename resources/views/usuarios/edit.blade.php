@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Usuário'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar usuário</h1>
            <p class="subtitle">Atualize dados de acesso, perfil e senha do usuário.</p>
        </div>
        <a class="btn" href="{{ route('usuarios.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('usuarios.update', $usuario->id) }}">
        @csrf
        @method('PUT')

        @include('usuarios.partials.dados-acesso')

        <div class="actions">
            <a class="btn" href="{{ route('usuarios.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar usuário</button>
        </div>
    </form>
@endsection
