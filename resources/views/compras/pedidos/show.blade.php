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
            @if (($canApproveOrders ?? false) && $order->can_approve)
                <form
                    method="post"
                    action="{{ route('compras.pedidos.approve', $order->id) }}"
                    data-purchase-order-approval-form
                    data-requires-without-invoice-confirmation="{{ $order->has_linked_invoices ? '0' : '1' }}"
                    data-requires-divergence-confirmation="{{ $order->has_invoice_divergences ? '1' : '0' }}"
                >
                    @csrf
                    <input type="hidden" name="confirmar_aprovacao" value="1">
                    <button class="btn primary" type="submit">Aprovar pedido</button>
                </form>
            @endif
            @if (($canApproveOrders ?? false) && $order->can_reject)
                <form method="post" action="{{ route('compras.pedidos.reject', $order->id) }}" onsubmit="return confirm('Rejeitar este pedido fiscal?')">
                    @csrf
                    <button class="btn danger" type="submit">Rejeitar pedido</button>
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

@push('scripts')
    @include('compras.pedidos.partials.approval-script')
@endpush
