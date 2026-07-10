<section class="panel">
    <div class="panel-head">
        <h2>Itens do pedido</h2>
        <button class="btn" type="button" data-add-item>+ Novo item</button>
    </div>
    <div class="panel-body">
        <div id="items" data-existing-items='@json($formItems ?? [])'></div>
        <div class="actions">
            <strong>Total do pedido: <span id="orderTotal">R$ 0,00</span></strong>
        </div>
    </div>
</section>

@include('compras.pedidos.partials.item-template')
