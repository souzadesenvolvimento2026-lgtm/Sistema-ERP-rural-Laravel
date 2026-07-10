@extends('layouts.farmfort', ['title' => 'FarmFort - Novo Usuário'])

@php
    $modoSistema = in_array((string)session('perfil'), ['administrador_sistema', 'gerencia_sistema'], true);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Novo usuário</h1>
            <p class="subtitle">
                {{ $modoSistema ? 'Cadastro de login interno FarmFort com acesso administrativo ao sistema.' : 'Cadastro de login vinculado à fazenda atual.' }}
            </p>
        </div>
        <a class="btn" href="{{ route('usuarios.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('usuarios.store') }}">
        @csrf

        @include('usuarios.partials.dados-acesso')

        <div class="actions">
            <a class="btn" href="{{ route('usuarios.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar usuário</button>
        </div>
    </form>
@endsection
