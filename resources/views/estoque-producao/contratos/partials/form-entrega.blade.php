<section class="panel" id="form-entrega-contrato">
    <div class="panel-head"><h2>Nova entrega</h2></div>
    <form method="POST" action="{{ route('estoque-producao.contratos.entrega') }}" class="form-grid">
        @csrf

        <label>
            Contrato *
            <select name="contrato_id" required data-contrato-entrega-select>
                <option value="">Selecione</option>
                @foreach ($contratos as $contrato)
                    <option value="{{ $contrato->id }}" data-unidade="{{ $contrato->unidade }}" @selected((string)old('contrato_id') === (string)$contrato->id)>{{ $contrato->numero }} - {{ $contrato->produto ?: $contrato->contraparte }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Data *
            <input type="date" name="data_entrega" value="{{ old('data_entrega', now()->format('Y-m-d')) }}" required>
        </label>

        <label>
            Quantidade *
            <input name="quantidade" value="{{ old('quantidade') }}" required inputmode="decimal" placeholder="0,00">
        </label>

        <label>
            Unidade
            <input name="unidade" value="{{ old('unidade', 'sc') }}" maxlength="30" data-contrato-entrega-unidade>
        </label>

        <label>
            Valor
            <input name="valor" value="{{ old('valor') }}" inputmode="decimal" placeholder="0,00">
        </label>

        <label class="span-2">
            Observações
            <textarea name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
        </label>

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar entrega</button>
        </div>
    </form>
</section>
