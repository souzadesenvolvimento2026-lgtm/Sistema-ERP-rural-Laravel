@extends('layouts.farmfort', ['title' => 'FarmFort - Editar Pedido'])

@section('content')
    <div class="page-head">
        <div>
            <h1>Editar Pedido</h1>
            <p class="subtitle">Ajuste os dados e itens antes da aprovacao.</p>
        </div>
        <a class="btn" href="{{ route('compras.pedidos.show', $order->id) }}">Voltar</a>
    </div>

    @php
        $formItems = $items->map(fn ($item) => [
            'product_code' => $item->product_code,
            'description' => $item->description,
            'categoria_id' => $item->categoria_id,
            'patrimonio_id' => $item->patrimonio_id,
            'patrimonio_uso' => $item->patrimonio_uso,
            'patrimonio_quantidade' => $item->patrimonio_quantidade,
            'unit' => $item->unit,
            'quantity' => $item->quantity,
            'unit_value' => $item->unit_value,
        ])->values();
    @endphp

    <form method="post" action="{{ route('compras.pedidos.update', $order->id) }}" id="pedidoForm">
        @csrf
        @method('PUT')

        @include('compras.pedidos.partials.dados-pedido')
        @include('compras.pedidos.partials.itens-pedido')

        <div class="actions">
            <a class="btn" href="{{ route('compras.pedidos.show', $order->id) }}">Cancelar</a>
            <button class="btn primary" type="submit">Salvar pedido</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/pedido-form.js') }}"></script>
@endpush
