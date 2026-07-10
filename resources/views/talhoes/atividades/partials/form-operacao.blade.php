<label class="span-2">
    Descrição *
    <input name="descricao" value="{{ old('descricao') }}" required maxlength="180">
</label>

<label>
    Área executada
    <input name="area_executada" value="{{ old('area_executada') }}" inputmode="decimal" placeholder="0,00">
</label>

<label>
    Responsável
    <input name="responsavel" value="{{ old('responsavel') }}" maxlength="120">
</label>

<label>
    Serviço
    <input name="servico" value="{{ old('servico') }}" maxlength="180">
</label>

<label>
    Produto
    <input name="produto" value="{{ old('produto') }}" maxlength="120">
</label>

<label>
    Dose
    <input name="dose" value="{{ old('dose') }}" maxlength="60">
</label>

<label>
    Custo estimado
    <input name="custo_estimado" value="{{ old('custo_estimado') }}" inputmode="decimal" placeholder="0,00">
</label>
