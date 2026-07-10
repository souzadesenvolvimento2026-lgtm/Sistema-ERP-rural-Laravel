<section class="panel">
    <div class="panel-head"><h2>Dados do produto</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field wide">
                <label>Produto</label>
                <input name="descricao_generica" value="{{ old('descricao_generica', $produto->descricao_generica ?? '') }}" required>
            </div>
            <div class="field">
                <label>Unidade</label>
                <input name="unidade_medida" value="{{ old('unidade_medida', $produto->unidade_medida ?? 'un') }}">
            </div>
            <div class="field">
                <label>Código interno</label>
                <input name="codigo_interno" value="{{ old('codigo_interno', $produto->codigo_interno ?? '') }}">
            </div>
            <div class="field">
                <label>Código fornecedor</label>
                <input name="codigo_fornecedor" value="{{ old('codigo_fornecedor', $produto->codigo_fornecedor ?? '') }}">
            </div>
            <div class="field">
                <label>Categoria</label>
                <select name="categoria_id">
                    <option value="">Sem categoria</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected((string)old('categoria_id', $produto->categoria_id ?? '') === (string)$categoria->id)>{{ $categoria->nome }} ({{ $categoria->tipo }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Grupo</label>
                <input name="grupo" value="{{ old('grupo', $produto->grupo ?? '') }}">
            </div>
            <div class="field">
                <label>Subgrupo</label>
                <input name="subgrupo" value="{{ old('subgrupo', $produto->subgrupo ?? '') }}">
            </div>
            <div class="field">
                <label>Marca</label>
                <input name="marca" value="{{ old('marca', $produto->marca ?? '') }}">
            </div>
            <div class="field">
                <label>NCM</label>
                <input name="ncm" value="{{ old('ncm', $produto->ncm ?? '') }}">
            </div>
            <div class="field">
                <label>CEST</label>
                <input name="cest" value="{{ old('cest', $produto->cest ?? '') }}">
            </div>
            <div class="field">
                <label>CFOP entrada</label>
                <input name="cfop_entrada" value="{{ old('cfop_entrada', $produto->cfop_entrada ?? '') }}">
            </div>
            <div class="field">
                <label>CST ICMS</label>
                <input name="cst_icms" value="{{ old('cst_icms', $produto->cst_icms ?? '') }}">
            </div>
            <div class="field">
                <label>CSOSN</label>
                <input name="csosn" value="{{ old('csosn', $produto->csosn ?? '') }}">
            </div>
            <div class="field">
                <label>CST PIS</label>
                <input name="cst_pis" value="{{ old('cst_pis', $produto->cst_pis ?? '') }}">
            </div>
            <div class="field">
                <label>CST COFINS</label>
                <input name="cst_cofins" value="{{ old('cst_cofins', $produto->cst_cofins ?? '') }}">
            </div>
            <div class="field">
                <label>ICMS %</label>
                <input name="aliquota_icms" value="{{ old('aliquota_icms', isset($produto) ? number_format((float)($produto->aliquota_icms ?? 0), 2, ',', '.') : '') }}" inputmode="decimal">
            </div>
            <div class="field">
                <label>PIS %</label>
                <input name="aliquota_pis" value="{{ old('aliquota_pis', isset($produto) ? number_format((float)($produto->aliquota_pis ?? 0), 2, ',', '.') : '') }}" inputmode="decimal">
            </div>
            <div class="field">
                <label>COFINS %</label>
                <input name="aliquota_cofins" value="{{ old('aliquota_cofins', isset($produto) ? number_format((float)($produto->aliquota_cofins ?? 0), 2, ',', '.') : '') }}" inputmode="decimal">
            </div>
            <div class="field">
                <label>IPI %</label>
                <input name="aliquota_ipi" value="{{ old('aliquota_ipi', isset($produto) ? number_format((float)($produto->aliquota_ipi ?? 0), 2, ',', '.') : '') }}" inputmode="decimal">
            </div>
            <div class="field">
                <label>Origem</label>
                <input name="origem_mercadoria" value="{{ old('origem_mercadoria', $produto->origem_mercadoria ?? '') }}">
            </div>
            <div class="field">
                <label>Tipo item</label>
                <input name="tipo_item" value="{{ old('tipo_item', $produto->tipo_item ?? '') }}">
            </div>
            <div class="field">
                <label>Codigo ANP</label>
                <input name="codigo_anp" value="{{ old('codigo_anp', $produto->codigo_anp ?? '') }}">
            </div>
            <div class="field full">
                <label>Descrição da nota</label>
                <input name="descricao_original_nf" value="{{ old('descricao_original_nf', $produto->descricao_original_nf ?? '') }}">
            </div>
            <div class="field full">
                <label>Descrição detalhada</label>
                <textarea name="descricao_detalhada">{{ old('descricao_detalhada', $produto->descricao_detalhada ?? '') }}</textarea>
            </div>
            <div class="field full">
                <label>Informações fiscais</label>
                <textarea name="informacoes_fiscais">{{ old('informacoes_fiscais', $produto->informacoes_fiscais ?? '') }}</textarea>
            </div>
            <div class="field full">
                <label>Descricao interna</label>
                <textarea name="descricao_interna">{{ old('descricao_interna', $produto->descricao_interna ?? '') }}</textarea>
            </div>
            <div class="field full">
                <label>Observacoes fiscais</label>
                <textarea name="observacoes_fiscais">{{ old('observacoes_fiscais', $produto->observacoes_fiscais ?? '') }}</textarea>
            </div>
        </div>
    </div>
</section>
