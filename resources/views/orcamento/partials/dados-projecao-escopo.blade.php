<div class="field">
    <label>Tipo</label>
    <select name="tipo_lancamento" required>
        <option value="despesa" @selected(old('tipo_lancamento', $projecao->tipo_lancamento ?? 'despesa') === 'despesa')>Despesa</option>
        <option value="receita" @selected(old('tipo_lancamento', $projecao->tipo_lancamento ?? '') === 'receita')>Receita</option>
    </select>
</div>

<div class="field">
    <label>Safra</label>
    <select name="safra_id">
        <option value="">Sem safra</option>
        @foreach ($safras as $safra)
            <option value="{{ $safra->id }}" @selected((string)old('safra_id', $projecao->safra_id ?? '') === (string)$safra->id)>{{ $safra->descricao }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label>Cultura</label>
    <select name="cultura_id">
        <option value="">Não informada</option>
        @foreach ($culturas as $cultura)
            <option value="{{ $cultura->id }}" @selected((string)old('cultura_id', $projecao->cultura_id ?? '') === (string)$cultura->id)>{{ $cultura->nome }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label>Tipo de safra</label>
    <select name="tipo_safra" required>
        <option value="principal" @selected(old('tipo_safra', $projecao->tipo_safra ?? 'principal') === 'principal')>Principal</option>
        <option value="safrinha" @selected(old('tipo_safra', $projecao->tipo_safra ?? '') === 'safrinha')>Safrinha</option>
    </select>
</div>

<div class="field">
    <label>Ano safra</label>
    <input name="ano_safra" value="{{ old('ano_safra', $projecao->ano_safra ?? date('Y')) }}" required>
</div>

<div class="field">
    <label>Mês referência</label>
    <input type="date" name="mes_referencia" value="{{ old('mes_referencia', $projecao->mes_referencia ?? date('Y-m-01')) }}" required>
</div>

<div class="field">
    <label>Categoria</label>
    <select name="categoria_id" required>
        <option value="">Selecione</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}" @selected((string)old('categoria_id', $projecao->categoria_id ?? '') === (string)$categoria->id)>{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
        @endforeach
    </select>
</div>
