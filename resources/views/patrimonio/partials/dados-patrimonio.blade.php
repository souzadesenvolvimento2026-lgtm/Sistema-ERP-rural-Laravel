@php
    $patrimonio = $patrimonio ?? null;
    $valor = fn (string $campo, mixed $padrao = '') => old($campo, $patrimonio?->{$campo} ?? $padrao);
    $temNotaFiscal = old('aquisicao_nova_nf') || filled($patrimonio?->nota_fiscal_numero) || filled($patrimonio?->nota_fiscal_arquivo);
    $controlaHorimetro = (bool) old('controla_horimetro', $patrimonio?->controla_horimetro ?? false);
    $controlaOdometro = (bool) old('controla_odometro', $patrimonio?->controla_odometro ?? false);
@endphp

<div class="ff-patrimony-form-grid" data-patrimony-form>
    <label class="ff-patrimony-field ff-patrimony-field-wide">
        <span>Nome *</span>
        <input class="form-control" name="nome" value="{{ $valor('nome') }}" required>
    </label>

    <label class="ff-patrimony-field">
        <span>Tipo *</span>
        <select class="form-select" name="tipo" required data-patrimony-type>
            @foreach ($tipos as $value => $label)
                <option value="{{ $value }}" @selected($valor('tipo', 'outro') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="ff-patrimony-field" data-patrimony-type-other>
        <span>Descreva o patrimônio</span>
        <input class="form-control" name="tipo_outro" value="{{ $valor('tipo_outro') }}" placeholder="Ex: Imóvel rural, lote, equipamento">
    </label>

    <label class="ff-patrimony-field">
        <span>Marca / modelo</span>
        <input class="form-control" name="marca_modelo" value="{{ $valor('marca_modelo') }}">
    </label>

    <label class="ff-patrimony-field">
        <span>Identificação</span>
        <input class="form-control" name="identificacao" value="{{ $valor('identificacao') }}">
    </label>

    <label class="ff-patrimony-field">
        <span>Ano</span>
        <input class="form-control" name="ano" inputmode="numeric" value="{{ $valor('ano') }}">
    </label>

    <label class="ff-patrimony-field">
        <span>Preço do patrimônio</span>
        <input class="form-control" name="valor_aquisicao" inputmode="decimal" value="{{ old('valor_aquisicao', $patrimonio?->valor_aquisicao ?? '') }}">
    </label>

    <label class="ff-patrimony-field">
        <span>Data de aquisição</span>
        <input class="form-control" type="date" name="data_aquisicao" value="{{ $valor('data_aquisicao') }}">
    </label>

    <label class="ff-patrimony-field">
        <span>Fornecedor</span>
        <input class="form-control" name="fornecedor" value="{{ $valor('fornecedor') }}">
    </label>

    <label class="ff-patrimony-field">
        <span>CNPJ/CPF fornecedor</span>
        <input class="form-control" name="fornecedor_doc" value="{{ $valor('fornecedor_doc') }}">
    </label>

    <div class="ff-patrimony-check-row ff-patrimony-field-wide">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="aquisicao_nova_nf" value="1" data-patrimony-nf-toggle @checked($temNotaFiscal)>
            <span class="form-check-label">Aquisição nova com NF</span>
        </label>
    </div>

    <div class="ff-patrimony-nf-grid ff-patrimony-field-full" data-patrimony-nf-fields>
        <label class="ff-patrimony-field">
            <span>Número da NF</span>
            <input class="form-control" name="nota_fiscal_numero" value="{{ $valor('nota_fiscal_numero') }}">
        </label>

        <label class="ff-patrimony-field">
            <span>Série da NF</span>
            <input class="form-control" name="nota_fiscal_serie" value="{{ $valor('nota_fiscal_serie') }}">
        </label>

        <label class="ff-patrimony-field ff-patrimony-field-wide">
            <span>Chave de acesso da NF</span>
            <input class="form-control" name="nota_fiscal_chave" value="{{ $valor('nota_fiscal_chave') }}" maxlength="60">
        </label>

        <label class="ff-patrimony-field ff-patrimony-field-wide">
            <span>Arquivo da NF</span>
            <input class="form-control" type="file" name="nota_fiscal_arquivo" accept=".pdf,.jpg,.jpeg,.png">
        </label>
    </div>

    <div class="ff-patrimony-check-row">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="controla_horimetro" value="1" data-patrimony-meter-toggle="horimetro" @checked($controlaHorimetro)>
            <span class="form-check-label">Controla horímetro</span>
        </label>
    </div>

    <label class="ff-patrimony-field" data-patrimony-meter-field="horimetro">
        <span>Horímetro atual</span>
        <input class="form-control" name="horimetro_atual" inputmode="decimal" value="{{ $valor('horimetro_atual') }}">
    </label>

    <div class="ff-patrimony-check-row">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="controla_odometro" value="1" data-patrimony-meter-toggle="odometro" @checked($controlaOdometro)>
            <span class="form-check-label">Controla odômetro</span>
        </label>
    </div>

    <label class="ff-patrimony-field" data-patrimony-meter-field="odometro">
        <span>Odômetro atual</span>
        <input class="form-control" name="odometro_atual" inputmode="decimal" value="{{ $valor('odometro_atual') }}">
    </label>

    <label class="ff-patrimony-field ff-patrimony-field-full">
        <span>Descrição / observações</span>
        <textarea class="form-control" name="descricao_patrimonio" rows="3">{{ $valor('descricao_patrimonio') }}</textarea>
    </label>
</div>
