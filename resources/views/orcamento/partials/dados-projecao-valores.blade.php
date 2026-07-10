<div class="field">
    <label>Quantidade</label>
    <input name="quantidade" inputmode="decimal" value="{{ old('quantidade', $projecao->quantidade ?? '') }}">
</div>

<div class="field">
    <label>Unidade</label>
    <input name="unidade" value="{{ old('unidade', $projecao->unidade ?? '') }}">
</div>

<div class="field">
    <label>Valor unitário</label>
    <input name="valor_unitario" inputmode="decimal" value="{{ old('valor_unitario', $projecao->valor_unitario ?? '') }}">
</div>

<div class="field">
    <label>Valor projetado</label>
    <input name="valor_projetado" inputmode="decimal" value="{{ old('valor_projetado', $projecao->valor_projetado ?? '') }}" required>
</div>

<div class="field full">
    <label>Observações</label>
    <textarea name="observacoes">{{ old('observacoes', $projecao->observacoes ?? '') }}</textarea>
</div>
