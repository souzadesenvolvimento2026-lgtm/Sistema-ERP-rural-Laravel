<section class="stats">
    <div class="stat"><span>Status</span><strong>{{ $order->status_label }}</strong></div>
    <div class="stat"><span>Valor total</span><strong>R$ {{ number_format($order->total_value, 2, ',', '.') }}</strong></div>
    <div class="stat"><span>Fornecedor</span><strong>{{ $order->supplier_name ?: '-' }}</strong></div>
    <div class="stat"><span>Propriedade</span><strong>{{ $order->propriedade_nome ?: '-' }}</strong></div>
</section>
