<section class="panel">
    <div class="panel-head"><h2>Dados do talhão</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field wide">
                <label>Nome</label>
                <input name="nome" value="{{ old('nome', $talhao->nome ?? '') }}" required>
            </div>
            <div class="field">
                <label>Tipo de geometria</label>
                <select name="geometria_tipo">
                    <option value="">Não informada</option>
                    @foreach (['polygon' => 'Polígono', 'line' => 'Linha', 'point' => 'Ponto'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('geometria_tipo', $talhao->geometria_tipo ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Área útil</label>
                <input name="area" inputmode="decimal" value="{{ old('area', isset($talhao) && $talhao->area !== null ? number_format($talhao->area, 2, ',', '.') : '') }}">
            </div>
            <div class="field">
                <label>Área bruta</label>
                <input name="area_bruta" inputmode="decimal" value="{{ old('area_bruta', isset($talhao) && $talhao->area_bruta !== null ? number_format($talhao->area_bruta, 2, ',', '.') : '') }}">
            </div>
            <div class="field">
                <label>Área excluída</label>
                <input name="area_excluida_ha" inputmode="decimal" value="{{ old('area_excluida_ha', isset($talhao) && $talhao->area_excluida_ha !== null ? number_format($talhao->area_excluida_ha, 2, ',', '.') : '') }}">
            </div>
            <div class="field">
                <label>Latitude</label>
                <input name="latitude" inputmode="decimal" value="{{ old('latitude', $talhao->latitude ?? '') }}">
            </div>
            <div class="field">
                <label>Longitude</label>
                <input name="longitude" inputmode="decimal" value="{{ old('longitude', $talhao->longitude ?? '') }}">
            </div>
            <div class="field">
                <label>Pivô ativo</label>
                <select name="pivo_ativo">
                    <option value="0" @selected((string)old('pivo_ativo', isset($talhao) ? (int)$talhao->pivo_ativo : 0) === '0')>Não</option>
                    <option value="1" @selected((string)old('pivo_ativo', isset($talhao) ? (int)$talhao->pivo_ativo : 0) === '1')>Sim</option>
                </select>
            </div>
            <div class="field">
                <label>Pivô latitude</label>
                <input name="pivo_lat" inputmode="decimal" value="{{ old('pivo_lat', $talhao->pivo_lat ?? '') }}">
            </div>
            <div class="field">
                <label>Pivô longitude</label>
                <input name="pivo_lng" inputmode="decimal" value="{{ old('pivo_lng', $talhao->pivo_lng ?? '') }}">
            </div>
            <div class="field">
                <label>Pivô raio (m)</label>
                <input name="pivo_raio_m" inputmode="decimal" value="{{ old('pivo_raio_m', $talhao->pivo_raio_m ?? '') }}">
            </div>
            <div class="field">
                <label>Pivô área (ha)</label>
                <input name="pivo_area_ha" inputmode="decimal" value="{{ old('pivo_area_ha', isset($talhao) && $talhao->pivo_area_ha !== null ? number_format($talhao->pivo_area_ha, 2, ',', '.') : '') }}">
            </div>
            <div class="field full">
                <label>Descrição</label>
                <textarea name="descricao">{{ old('descricao', $talhao->descricao ?? '') }}</textarea>
            </div>
        </div>
    </div>
</section>
