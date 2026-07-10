<section class="panel">
    <div class="panel-head"><h2>Novo certificado</h2></div>
    <form method="POST" action="{{ route('fiscal.certificados.store') }}" class="form-grid" enctype="multipart/form-data">
        @csrf

        <label>
            Nome de identificação *
            <input name="nome_identificacao" value="{{ old('nome_identificacao') }}" required maxlength="120" placeholder="Ex: Certificado Fazenda 2026">
        </label>

        <label>
            Tipo *
            <select name="tipo_certificado" required>
                <option value="A1" @selected(old('tipo_certificado', 'A1') === 'A1')>A1 arquivo</option>
                <option value="A3" @selected(old('tipo_certificado') === 'A3')>A3 token</option>
            </select>
        </label>

        <label>
            Ambiente *
            <select name="ambiente" required>
                <option value="homologacao" @selected(old('ambiente', 'homologacao') === 'homologacao')>Homologação</option>
                <option value="producao" @selected(old('ambiente') === 'producao')>Produção</option>
            </select>
        </label>

        <label>
            Titular
            <input name="titular" value="{{ old('titular') }}" maxlength="180">
        </label>

        <label>
            CPF/CNPJ
            <input name="cpf_cnpj" value="{{ old('cpf_cnpj', $propriedade?->cnpj_cpf ?? '') }}" maxlength="20">
        </label>

        <label>
            Número de série
            <input name="numero_serie" value="{{ old('numero_serie') }}" maxlength="120">
        </label>

        <label>
            Emissor
            <input name="emissor" value="{{ old('emissor') }}" maxlength="180">
        </label>

        <label>
            Validade início
            <input type="date" name="validade_inicio" value="{{ old('validade_inicio') }}">
        </label>

        <label>
            Validade fim
            <input type="date" name="validade_fim" value="{{ old('validade_fim') }}">
        </label>

        <label>
            Arquivo A1
            <input type="file" name="certificado" accept=".pfx,.p12">
        </label>

        <label>
            Senha
            <input type="password" name="senha_certificado" autocomplete="new-password">
        </label>

        <label>
            Principal
            <select name="principal">
                <option value="1" @selected(old('principal', '1') === '1')>Sim</option>
                <option value="0" @selected(old('principal') === '0')>Não</option>
            </select>
        </label>

        <label class="span-2">
            Observações
            <textarea name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
        </label>

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar certificado</button>
        </div>
    </form>
</section>
