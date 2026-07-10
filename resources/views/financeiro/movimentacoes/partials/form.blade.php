<section class="panel">
    <div class="panel-head"><h2>Nova movimentação</h2></div>
    <form method="POST" action="{{ route('financeiro.movimentacoes.store') }}" class="form-grid">
        @csrf

        <label>
            Data *
            <input type="date" name="data_movimento" value="{{ old('data_movimento', now()->format('Y-m-d')) }}" required>
        </label>

        <label>
            Conta *
            <select name="conta_id" required>
                <option value="">Selecione</option>
                @foreach ($contas as $conta)
                    <option value="{{ $conta->id }}" @selected((string)old('conta_id') === (string)$conta->id)>{{ $conta->nome }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Tipo *
            <select name="tipo" required>
                <option value="entrada" @selected(old('tipo') === 'entrada')>Entrada</option>
                <option value="saida" @selected(old('tipo') === 'saida')>Saída</option>
            </select>
        </label>

        <label>
            Origem *
            <select name="origem" required>
                <option value="manual" @selected(old('origem') === 'manual')>Manual</option>
                <option value="extrato" @selected(old('origem') === 'extrato')>Extrato</option>
                <option value="ofx" @selected(old('origem') === 'ofx')>OFX</option>
                <option value="csv" @selected(old('origem') === 'csv')>CSV</option>
            </select>
        </label>

        <label class="span-2">
            Descrição *
            <input name="descricao" value="{{ old('descricao') }}" required maxlength="180">
        </label>

        <label>
            Valor *
            <input name="valor" value="{{ old('valor') }}" required inputmode="decimal" placeholder="0,00">
        </label>

        <div class="form-actions span-2">
            <button class="btn primary" type="submit">Salvar movimentação</button>
        </div>
    </form>
</section>
