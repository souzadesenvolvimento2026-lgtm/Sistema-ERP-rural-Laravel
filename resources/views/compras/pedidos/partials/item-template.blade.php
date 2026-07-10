<template id="itemTemplate">
    <div class="item-card" data-item>
        <div class="form-grid">
            <div class="field">
                <label>Código</label>
                <input name="item_product_code[]">
            </div>
            <div class="field wide">
                <label>Descrição</label>
                <input name="item_description[]" required>
            </div>
            <div class="field">
                <label>Categoria</label>
                <select name="item_categoria_id[]">
                    <option value="">Sem categoria</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}">{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Patrimônio</label>
                <select name="item_patrimonio_id[]" data-asset>
                    <option value="">Sem vínculo</option>
                    @foreach ($patrimonios as $patrimonio)
                        <option value="{{ $patrimonio->id }}">{{ $patrimonio->nome }}{{ $patrimonio->marca_modelo ? ' - '.$patrimonio->marca_modelo : '' }}{{ !$patrimonio->ativo ? ' (inativo)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" data-asset-use-wrap>
                <label>Uso no patrimônio</label>
                <select name="item_patrimonio_uso[]" data-asset-use>
                    <option value="estoque">Não usar agora</option>
                    <option value="total">Usar tudo no patrimônio</option>
                    <option value="parcial">Usar parte e enviar sobra ao estoque</option>
                </select>
            </div>
            <div class="field" data-asset-quantity-wrap style="display:none">
                <label>Quantidade usada</label>
                <input name="item_patrimonio_quantidade[]" data-asset-quantity inputmode="decimal">
            </div>
            <div class="field">
                <label>Unidade</label>
                <select name="item_unit[]" required>
                    <option value="">Selecione</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit }}">{{ $unit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Quantidade</label>
                <input name="item_quantity[]" data-quantity inputmode="decimal" required>
            </div>
            <div class="field">
                <label>Valor unitário</label>
                <input name="item_unit_value[]" data-unit-value inputmode="decimal" required>
            </div>
            <div class="field">
                <label>Total do item</label>
                <input data-item-total readonly value="R$ 0,00">
            </div>
            <div class="field full actions">
                <button class="btn danger" type="button" data-remove-item>Remover item</button>
            </div>
        </div>
    </div>
</template>
