<section class="panel">
    <div class="panel-head">
        <h2>Dados do patrimonio</h2>
        <span class="pill {{ $patrimonio->ativo ? 'success' : 'danger' }}">{{ $patrimonio->status }}</span>
    </div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field">
                <label>Tipo</label>
                <strong>{{ $patrimonio->tipo }}</strong>
            </div>
            <div class="field">
                <label>Modelo</label>
                <strong>{{ $patrimonio->marca_modelo }}</strong>
            </div>
            <div class="field">
                <label>Identificacao</label>
                <strong>{{ $patrimonio->identificacao }}</strong>
            </div>
            <div class="field">
                <label>Ano</label>
                <strong>{{ $patrimonio->ano }}</strong>
            </div>
            <div class="field">
                <label>Aquisicao</label>
                <strong>{{ $patrimonio->data_aquisicao }}</strong>
            </div>
            <div class="field">
                <label>Fornecedor</label>
                <strong>{{ $patrimonio->fornecedor }}</strong>
            </div>
            <div class="field wide">
                <form method="post" action="{{ route('patrimonio.update-value', $patrimonio->id) }}" class="inline-form">
                    @csrf
                    <label>Preco do patrimonio</label>
                    <div class="inline-actions">
                        <input name="valor_aquisicao" inputmode="decimal" value="{{ old('valor_aquisicao', number_format($patrimonio->valor_aquisicao_raw, 2, ',', '.')) }}">
                        <button class="btn small" type="submit">Salvar preco</button>
                    </div>
                </form>
            </div>
            @if ($patrimonio->nota_fiscal_numero !== '-' || $patrimonio->nf_entrada_id || $patrimonio->documento_id || $patrimonio->nota_fiscal_arquivo !== '')
                <div class="field">
                    <label>Nota fiscal</label>
                    <strong>{{ $patrimonio->nota_fiscal_numero }}</strong>
                    @if ($patrimonio->nota_fiscal_serie !== '-')
                        <span class="muted">Serie {{ $patrimonio->nota_fiscal_serie }}</span>
                    @endif
                </div>
                <div class="field wide">
                    <label>Vinculos fiscais</label>
                    <div class="inline-actions">
                        @if ($patrimonio->nf_entrada_id)
                            <a class="btn small" href="{{ route('fiscal.entrada-nf.index', ['search' => $patrimonio->nota_fiscal_numero]) }}">Entrada fiscal</a>
                        @endif
                        @if ($patrimonio->documento_id)
                            <a class="btn small" href="{{ route('fiscal.documentos.arquivo', $patrimonio->documento_id) }}">Documento NF</a>
                        @endif
                        @if ($patrimonio->nota_fiscal_arquivo !== '')
                            <a class="btn small" href="{{ asset('uploads/comprovantes/'.$patrimonio->nota_fiscal_arquivo) }}" target="_blank">Arquivo NF</a>
                        @endif
                    </div>
                </div>
            @endif
            <div class="field">
                <label>Horimetro</label>
                <strong>{{ $patrimonio->horimetro }}</strong>
            </div>
            <div class="field">
                <label>Odometro</label>
                <strong>{{ $patrimonio->odometro }}</strong>
            </div>
            @if ($patrimonio->descricao !== '-')
                <div class="field full">
                    <label>Descricao</label>
                    <p>{{ $patrimonio->descricao }}</p>
                </div>
            @endif
        </div>
    </div>
</section>
