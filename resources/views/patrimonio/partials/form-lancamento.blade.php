<section class="panel">
    <div class="panel-head"><h2>Novo lancamento</h2></div>
    <form method="POST" action="{{ route('patrimonio.lancamentos.store', $patrimonio->id) }}" enctype="multipart/form-data" class="panel-body">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>Tipo</label>
                <select name="tipo" required>
                    @foreach ($tiposLancamento as $value => $label)
                        <option value="{{ $value }}" @selected(old('tipo') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Data</label>
                <input type="date" name="data_lancamento" value="{{ old('data_lancamento', date('Y-m-d')) }}" required>
            </div>

            <div class="field wide">
                <label>Descricao</label>
                <input name="descricao" value="{{ old('descricao') }}" required>
            </div>

            <div class="field">
                <label>Fornecedor</label>
                <input name="fornecedor" value="{{ old('fornecedor') }}">
            </div>

            <div class="field">
                <label>Safra</label>
                <select name="safra_id">
                    <option value="">Sem safra</option>
                    @foreach ($safras as $safra)
                        <option value="{{ $safra->id }}" @selected((string)old('safra_id') === (string)$safra->id)>{{ $safra->descricao }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Talhao</label>
                <select name="talhao_id">
                    <option value="">Sem talhao</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao->id }}" @selected((string)old('talhao_id') === (string)$talhao->id)>{{ $talhao->nome }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Quantidade</label>
                <input name="quantidade" inputmode="decimal" value="{{ old('quantidade') }}">
            </div>

            <div class="field">
                <label>Unidade</label>
                <input name="unidade" value="{{ old('unidade') }}">
            </div>

            <div class="field">
                <label>Valor unitario</label>
                <input name="valor_unitario" inputmode="decimal" value="{{ old('valor_unitario') }}">
            </div>

            <div class="field">
                <label>Valor total</label>
                <input name="valor_total" inputmode="decimal" value="{{ old('valor_total') }}">
            </div>

            <div class="field">
                <label>Horimetro</label>
                <input name="horimetro" inputmode="decimal" value="{{ old('horimetro') }}">
            </div>

            <div class="field">
                <label>Odometro</label>
                <input name="odometro" inputmode="decimal" value="{{ old('odometro') }}">
            </div>

            <div class="field">
                <label>Proxima revisao</label>
                <input name="proxima_revisao_horas" inputmode="decimal" value="{{ old('proxima_revisao_horas') }}">
            </div>

            <div class="field">
                <label>Comprovante</label>
                <input type="file" name="comprovante" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <div class="field full">
                <label>Observacoes</label>
                <textarea name="observacoes">{{ old('observacoes') }}</textarea>
            </div>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit">Salvar lancamento</button>
        </div>
    </form>
</section>
