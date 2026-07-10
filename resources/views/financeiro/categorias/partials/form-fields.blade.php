<label>
    Categoria principal
    <select name="categoria_pai_id">
        <option value="">Esta é uma categoria principal</option>
        @foreach ($principais as $principal)
            @if (!$categoria || $principal->id !== $categoria->id)
                <option value="{{ $principal->id }}" @selected(($categoria->categoria_pai_id ?? null) == $principal->id)>{{ $principal->nome }}</option>
            @endif
        @endforeach
    </select>
</label>

<label>
    Nome *
    <input name="nome" value="{{ old('nome', $categoria->nome ?? '') }}" required maxlength="100">
</label>

<label>
    Tipo *
    <select name="tipo" required>
        @foreach ($tipos as $tipo)
            <option value="{{ $tipo }}" @selected(($categoria->tipo ?? 'outros') === $tipo)>{{ ucfirst($tipo) }}</option>
        @endforeach
    </select>
</label>

<label>
    Cor
    <input type="color" name="cor" value="{{ old('cor', $categoria->cor ?? '#6c757d') }}">
</label>

<label>
    Ícone
    <input name="icone" value="{{ old('icone', $categoria->icone ?? 'bi-tag') }}" maxlength="40">
</label>

<label>
    Ativa
    <select name="ativo">
        <option value="1" @selected(($categoria->ativo ?? 1) == 1)>Sim</option>
        <option value="0" @selected(($categoria->ativo ?? 1) == 0)>Não</option>
    </select>
</label>
