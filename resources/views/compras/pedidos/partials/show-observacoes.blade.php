@if ($order->notes)
    <section class="panel">
        <div class="panel-head"><h2>Observações</h2></div>
        <div class="panel-body">{{ $order->notes }}</div>
    </section>
@endif
