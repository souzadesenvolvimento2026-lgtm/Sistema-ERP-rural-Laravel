@php
    $selectedFornecedorId = (string) old('supplier_id', '');
@endphp

<section class="panel">
    <div class="panel-head"><h2>Dados do pedido</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field">
                <label>Número</label>
                <input name="order_number" value="{{ old('order_number', $order->order_number ?? '') }}" placeholder="Automático se vazio">
            </div>
            <div class="field">
                <label>Data</label>
                <input type="date" name="issue_date" value="{{ old('issue_date', isset($order) ? $order->issue_date : date('Y-m-d')) }}" required>
            </div>
            <div class="field wide">
                <label>Fornecedor cadastrado</label>
                <select name="supplier_id" data-purchase-supplier-select>
                    <option value="">Informar manualmente</option>
                    @foreach (($fornecedores ?? collect()) as $fornecedor)
                        <option
                            value="{{ $fornecedor->id }}"
                            data-name="{{ $fornecedor->nome }}"
                            data-document="{{ $fornecedor->documento }}"
                            @selected($selectedFornecedorId === (string) $fornecedor->id)
                        >
                            {{ $fornecedor->nome }}@if (! empty($fornecedor->documento_formatado)) — {{ $fornecedor->documento_formatado }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>CNPJ do fornecedor</label>
                <input name="supplier_cnpj" value="{{ old('supplier_cnpj', $order->supplier_cnpj ?? '') }}" data-purchase-supplier-document>
            </div>
            <div class="field wide">
                <label>Fornecedor</label>
                <input name="supplier_name" value="{{ old('supplier_name', $order->supplier_name ?? '') }}" data-purchase-supplier-name>
            </div>
            <div class="field full">
                <label>Observações</label>
                <textarea name="notes">{{ old('notes', $order->notes ?? '') }}</textarea>
            </div>
        </div>
    </div>
</section>
