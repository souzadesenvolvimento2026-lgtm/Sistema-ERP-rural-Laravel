@php
    $formItems = [];

    if (old('_pedido_modal') === 'create') {
        $descriptions = old('item_description', []);
        $productCodes = old('item_product_code', []);
        $categoryIds = old('item_categoria_id', []);
        $assetIds = old('item_patrimonio_id', []);
        $assetUses = old('item_patrimonio_uso', []);
        $assetQuantities = old('item_patrimonio_quantidade', []);
        $unitsOld = old('item_unit', []);
        $quantities = old('item_quantity', []);
        $unitValues = old('item_unit_value', []);

        foreach ($descriptions as $index => $description) {
            $formItems[] = [
                'product_code' => $productCodes[$index] ?? '',
                'description' => $description,
                'categoria_id' => $categoryIds[$index] ?? '',
                'patrimonio_id' => $assetIds[$index] ?? '',
                'patrimonio_uso' => $assetUses[$index] ?? 'estoque',
                'patrimonio_quantidade' => $assetQuantities[$index] ?? '',
                'unit' => $unitsOld[$index] ?? '',
                'quantity' => $quantities[$index] ?? '',
                'unit_value' => $unitValues[$index] ?? '',
            ];
        }
    }
@endphp

<div class="modal fade ff-purchase-order-modal" id="pedidoCreateModal" tabindex="-1" aria-labelledby="pedidoCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ff-purchase-order-dialog">
        <form method="post" action="{{ route('compras.pedidos.store') }}" class="modal-content" id="pedidoForm">
            @csrf
            <input type="hidden" name="_pedido_modal" value="create">

            <div class="modal-header">
                <h5 class="modal-title" id="pedidoCreateModalLabel">
                    <i class="bi bi-clipboard-plus me-2"></i>Novo Pedido Fiscal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="ff-purchase-order-grid">
                    <label class="ff-purchase-order-field">
                        <span>Número</span>
                        <input name="order_number" value="{{ old('order_number') }}" placeholder="Automático se vazio">
                    </label>

                    <label class="ff-purchase-order-field">
                        <span>Data</span>
                        <input type="date" name="issue_date" value="{{ old('issue_date', date('Y-m-d')) }}" required>
                    </label>

                    <label class="ff-purchase-order-field">
                        <span>Tipo</span>
                        <input value="Entrada / Compra" readonly>
                    </label>

                    <label class="ff-purchase-order-field">
                        <span>CNPJ do fornecedor *</span>
                        <input name="supplier_cnpj" value="{{ old('supplier_cnpj') }}" inputmode="numeric" required>
                    </label>

                    <label class="ff-purchase-order-field ff-purchase-order-field-supplier">
                        <span>Fornecedor *</span>
                        <input name="supplier_name" value="{{ old('supplier_name') }}" required>
                    </label>

                    <label class="ff-purchase-order-field ff-purchase-order-field-notes">
                        <span>Observações</span>
                        <input name="notes" value="{{ old('notes') }}">
                    </label>
                </div>

                <div class="ff-purchase-order-items-head">
                    <h6><i class="bi bi-list-check me-2"></i>Itens do pedido</h6>
                    <button class="btn btn-sm" type="button" data-add-item>
                        <i class="bi bi-plus-lg"></i> Novo item
                    </button>
                </div>

                <div id="items" class="ff-purchase-order-items" data-existing-items='@json($formItems)'></div>

                <div class="ff-purchase-order-total">
                    <span>Total do pedido</span>
                    <strong id="orderTotal">R$ 0,00</strong>
                </div>
            </div>

            <div class="modal-footer ff-modal-footer-split">
                <button type="button" class="btn cancel" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="bi bi-check2-square"></i> Criar pedido
                </button>
            </div>
        </form>
    </div>
</div>

@include('compras.pedidos.partials.item-template')

@if (old('_pedido_modal') === 'create')
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById('pedidoCreateModal');

                if (modalElement && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        </script>
    @endpush
@endif
