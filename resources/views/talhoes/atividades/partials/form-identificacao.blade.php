<label>
    Tipo *
    <select name="tipo" required>
        @foreach ($tipos as $value => $label)
            <option value="{{ $value }}" @selected(old('tipo', 'manejo') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>

<label>
    Safra
    <select name="safra_id">
        <option value="">Nenhuma</option>
        @foreach ($safras as $safra)
            <option value="{{ $safra->id }}" @selected((string)old('safra_id', $filtroSafraId) === (string)$safra->id)>{{ $safra->descricao }}</option>
        @endforeach
    </select>
</label>

<label>
    Talhão
    <select name="talhao_id">
        <option value="">Fazenda geral</option>
        @foreach ($talhoes as $talhao)
            <option value="{{ $talhao->id }}" @selected((string)old('talhao_id') === (string)$talhao->id)>{{ $talhao->nome }}</option>
        @endforeach
    </select>
</label>
