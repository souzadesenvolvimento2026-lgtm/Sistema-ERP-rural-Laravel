@php
    $patrimonio = $patrimonio ?? null;
    $valor = fn (string $campo, mixed $padrao = '') => old($campo, $patrimonio?->{$campo} ?? $padrao);
@endphp

<section class="panel">
    <div class="panel-head"><h2>Dados do patrimônio</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field wide">
                <label>Nome</label>
                <input name="nome" value="{{ $valor('nome') }}" required>
            </div>
            <div class="field">
                <label>Tipo</label>
                <select name="tipo" required>
                    @foreach ($tipos as $value => $label)
                        <option value="{{ $value }}" @selected($valor('tipo', 'outro') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Tipo outro</label>
                <input name="tipo_outro" value="{{ $valor('tipo_outro') }}">
            </div>
            <div class="field">
                <label>Marca/modelo</label>
                <input name="marca_modelo" value="{{ $valor('marca_modelo') }}">
            </div>
            <div class="field">
                <label>Identificação</label>
                <input name="identificacao" value="{{ $valor('identificacao') }}">
            </div>
            <div class="field">
                <label>Ano</label>
                <input name="ano" inputmode="numeric" value="{{ $valor('ano') }}">
            </div>
            <div class="field">
                <label>Valor de aquisição</label>
                <input name="valor_aquisicao" inputmode="decimal" value="{{ old('valor_aquisicao', $patrimonio?->valor_aquisicao ?? '') }}">
            </div>
            <div class="field">
                <label>Data de aquisição</label>
                <input type="date" name="data_aquisicao" value="{{ $valor('data_aquisicao') }}">
            </div>
            <div class="field">
                <label>Fornecedor</label>
                <input name="fornecedor" value="{{ $valor('fornecedor') }}">
            </div>
            <div class="field">
                <label>CNPJ/CPF fornecedor</label>
                <input name="fornecedor_doc" value="{{ $valor('fornecedor_doc') }}">
            </div>
            <div class="field">
                <label>Numero da NF</label>
                <input name="nota_fiscal_numero" value="{{ $valor('nota_fiscal_numero') }}">
            </div>
            <div class="field">
                <label>Serie da NF</label>
                <input name="nota_fiscal_serie" value="{{ $valor('nota_fiscal_serie') }}">
            </div>
            <div class="field wide">
                <label>Chave de acesso da NF</label>
                <input name="nota_fiscal_chave" value="{{ $valor('nota_fiscal_chave') }}" maxlength="60">
            </div>
            <div class="field">
                <label>Arquivo da NF</label>
                <input type="file" name="nota_fiscal_arquivo" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="field">
                <label>Controla horímetro</label>
                <select name="controla_horimetro">
                    <option value="0" @selected((string) $valor('controla_horimetro', 0) === '0')>Não</option>
                    <option value="1" @selected((string) $valor('controla_horimetro', 0) === '1')>Sim</option>
                </select>
            </div>
            <div class="field">
                <label>Horímetro atual</label>
                <input name="horimetro_atual" inputmode="decimal" value="{{ $valor('horimetro_atual') }}">
            </div>
            <div class="field">
                <label>Controla odômetro</label>
                <select name="controla_odometro">
                    <option value="0" @selected((string) $valor('controla_odometro', 0) === '0')>Não</option>
                    <option value="1" @selected((string) $valor('controla_odometro', 0) === '1')>Sim</option>
                </select>
            </div>
            <div class="field">
                <label>Odômetro atual</label>
                <input name="odometro_atual" inputmode="decimal" value="{{ $valor('odometro_atual') }}">
            </div>
            <div class="field full">
                <label>Descrição</label>
                <textarea name="descricao_patrimonio">{{ $valor('descricao_patrimonio') }}</textarea>
            </div>
        </div>
    </div>
</section>
