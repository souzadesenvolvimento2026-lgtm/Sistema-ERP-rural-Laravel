@extends('layouts.farmfort', ['title' => 'FarmFort - Novo Pedido'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Novo Pedido</h1>
            <p class="subtitle">Cadastro Laravel com múltiplos itens, categoria e vínculo opcional ao patrimônio.</p>
        </div>
        <a class="btn" href="{{ route('compras.pedidos.index') }}">Voltar</a>
    </div>

    <form method="post" action="{{ route('compras.pedidos.store') }}" id="pedidoForm">
        @csrf

        @include('compras.pedidos.partials.dados-pedido')
        @include('compras.pedidos.partials.itens-pedido')

        <div class="actions">
            <a class="btn" href="{{ route('compras.pedidos.index') }}">Cancelar</a>
            <button class="btn primary" type="submit">Criar pedido</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/pedido-form.js') }}"></script>
@endpush
