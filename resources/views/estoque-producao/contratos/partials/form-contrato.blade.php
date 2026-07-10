<section class="panel" id="form-contrato">
    <div class="panel-head"><h2>Novo contrato</h2></div>
    <form method="POST" action="{{ route('estoque-producao.contratos.store') }}" class="form-grid">
        @csrf

        <label>
            Tipo *
            <select name="tipo" required>
                @foreach ($tipos as $value => $label)
                    <option value="{{ $value }}" @selected(old('tipo') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Número *
            <input name="numero" value="{{ old('numero') }}" required maxlength="80">
        </label>

        <label>
            Data *
            <input type="date" name="data_contrato" value="{{ old('data_contrato', now()->format('Y-m-d')) }}" required>
        </label>

        <label>
            Vencimento
            <input type="date" name="data_vencimento" value="{{ old('data_vencimento') }}">
        </label>

        <label>
            Contraparte
            <input name="contraparte" value="{{ old('contraparte') }}" maxlength="150">
        </label>

        <label>
            Safra
            <select name="safra_id">
                <option value="">Nenhuma</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected((string)old('safra_id') === (string)$safra->id)>{{ $safra->descricao }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Produto
            <input name="produto" value="{{ old('produto') }}" maxlength="100" placeholder="Soja, milho...">
        </label>

        <label>
            Quantidade
            <input name="quantidade" value="{{ old('quantidade') }}" inputmode="decimal" placeholder="0,00">
        </label>

        <label>
            Unidade
            <input name="unidade" value="{{ old('unidade', 'sc') }}" maxlength="30">
        </label>

        <label>
            Preço unitário
            <input name="preco_unitario" value="{{ old('preco_unitario') }}" inputmode="decimal" placeholder="0,00">
        </label>

        <label>
            Valor total
            <input name="valor_total" value="{{ old('valor_total') }}" inputmode="decimal" placeholder="0,00">
        </label>

        <label class="span-2">
            Observações
            <textarea name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
        </label>

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar contrato</button>
        </div>
    </form>
</section>
