<section class="panel">
    <div class="panel-head"><h2>Dados da propriedade</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field wide">
                <label>Nome</label>
                <input name="nome" value="{{ old('nome', $propriedade->nome ?? '') }}" required>
            </div>
            <div class="field">
                <label>Plano</label>
                <select name="plano" required>
                    <option value="basico" @selected(old('plano', $propriedade->plano ?? '') === 'basico')>Básico</option>
                    <option value="avancado" @selected(old('plano', $propriedade->plano ?? '') === 'avancado')>Avançado</option>
                    <option value="premium" @selected(old('plano', $propriedade->plano ?? '') === 'premium')>Premium</option>
                </select>
            </div>
            <div class="field">
                <label>Município</label>
                <input name="municipio" value="{{ old('municipio', $propriedade->municipio ?? '') }}">
            </div>
            <div class="field">
                <label>UF</label>
                <input name="estado" maxlength="2" value="{{ old('estado', $propriedade->estado ?? '') }}">
            </div>
            <div class="field">
                <label>Área total</label>
                <input name="area_total" inputmode="decimal" value="{{ old('area_total', isset($propriedade) ? number_format((float)$propriedade->area_total, 2, ',', '.') : '') }}">
            </div>
            <div class="field">
                <label>Responsável</label>
                <input name="responsavel" value="{{ old('responsavel', $propriedade->responsavel ?? '') }}">
            </div>
            <div class="field">
                <label>Aprovador</label>
                <select name="aprovador_usuario_id">
                    <option value="">Nenhum</option>
                    @foreach (($aprovadores ?? collect()) as $aprovador)
                        <option value="{{ $aprovador->id }}" @selected((int)old('aprovador_usuario_id', $propriedade->aprovador_usuario_id ?? 0) === (int)$aprovador->id)>{{ $aprovador->nome }} - {{ $aprovador->perfil }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>CNPJ/CPF</label>
                <input name="cnpj_cpf" value="{{ old('cnpj_cpf', $propriedade->cnpj_cpf ?? '') }}">
            </div>
            <div class="field">
                <label>Inscrição estadual</label>
                <input name="inscricao_estadual" value="{{ old('inscricao_estadual', $propriedade->inscricao_estadual ?? '') }}">
            </div>
            <div class="field">
                <label>Latitude</label>
                <input name="latitude" inputmode="decimal" value="{{ old('latitude', $propriedade->latitude ?? '') }}">
            </div>
            <div class="field">
                <label>Longitude</label>
                <input name="longitude" inputmode="decimal" value="{{ old('longitude', $propriedade->longitude ?? '') }}">
            </div>
            <div class="field">
                <label>Região de cotação</label>
                <input name="regiao_cotacao" value="{{ old('regiao_cotacao', $propriedade->regiao_cotacao ?? '') }}">
            </div>
            <div class="field">
                <label>Pecuária ativa</label>
                <select name="pecuaria_ativa">
                    <option value="0" @selected((string)old('pecuaria_ativa', (int)($propriedade->pecuaria_ativa ?? 0)) === '0')>Não</option>
                    <option value="1" @selected((string)old('pecuaria_ativa', (int)($propriedade->pecuaria_ativa ?? 0)) === '1')>Sim</option>
                </select>
            </div>
            <div class="field full">
                <label>Arquivo geoespacial</label>
                <input type="file" name="kml_area" accept=".kml,.kmz,.shp,.zip">
            </div>
        </div>
    </div>
</section>
