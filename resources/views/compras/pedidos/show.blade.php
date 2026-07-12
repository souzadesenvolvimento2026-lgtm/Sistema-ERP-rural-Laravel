@extends('layouts.farmfort', ['title' => 'FarmFort - Pedido '.$order->order_number])

@section('content')
    <div class="page-head">
        <div>
            <h1>Pedido {{ $order->order_number }}</h1>
            <p class="subtitle">{{ $order->supplier_name }} · {{ \Illuminate\Support\Carbon::parse($order->issue_date)->format('d/m/Y') }}</p>
        </div>
        <div class="actions">
            @if ($order->can_edit)
                <a class="btn" href="{{ route('compras.pedidos.edit', $order->id) }}">Editar</a>
            @endif
            @if ($order->can_approve)
                <form method="post" action="{{ route('compras.pedidos.approve', $order->id) }}">
                    @csrf
                    <input type="hidden" name="confirmar_aprovacao" value="1">
                    <button class="btn primary" type="submit">Aprovar pedido</button>
                </form>
            @endif
            <a class="btn" href="{{ route('compras.pedidos.index') }}">Voltar</a>
        </div>
    </div>

    @include('compras.pedidos.partials.show-resumo')
    @include('compras.pedidos.partials.show-itens')
    @include('compras.pedidos.partials.show-notas')
    @include('compras.pedidos.partials.show-observacoes')
@endsection
