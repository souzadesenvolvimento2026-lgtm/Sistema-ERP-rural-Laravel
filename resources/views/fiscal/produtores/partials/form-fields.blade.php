<label>
    Nome *
    <input name="nome" value="{{ old('nome', $produtor->nome ?? '') }}" required maxlength="150">
</label>

<label>
    CPF/CNPJ
    <input name="documento" value="{{ old('documento', $produtor->documento ?? '') }}" maxlength="30">
</label>

<label>
    Participação (%)
    <input name="participacao_percentual" value="{{ old('participacao_percentual', isset($produtor) && $produtor->participacao_percentual !== null ? number_format($produtor->participacao_percentual, 2, ',', '.') : '') }}" inputmode="decimal" placeholder="0,00">
</label>
