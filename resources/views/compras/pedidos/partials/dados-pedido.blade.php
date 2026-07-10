<section class="panel">
    <div class="panel-head"><h2>Dados do pedido</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field">
                <label>Numero</label>
                <input name="order_number" value="{{ old('order_number', $order->order_number ?? '') }}" placeholder="Automatico se vazio">
            </div>
            <div class="field">
                <label>Data</label>
                <input type="date" name="issue_date" value="{{ old('issue_date', isset($order) ? $order->issue_date : date('Y-m-d')) }}" required>
            </div>
            <div class="field">
                <label>CNPJ do fornecedor</label>
                <input name="supplier_cnpj" value="{{ old('supplier_cnpj', $order->supplier_cnpj ?? '') }}" required>
            </div>
            <div class="field wide">
                <label>Fornecedor</label>
                <input name="supplier_name" value="{{ old('supplier_name', $order->supplier_name ?? '') }}" required>
            </div>
            <div class="field full">
                <label>Observacoes</label>
                <textarea name="notes">{{ old('notes', $order->notes ?? '') }}</textarea>
            </div>
        </div>
    </div>
</section>
