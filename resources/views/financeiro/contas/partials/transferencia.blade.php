<section class="panel">
    <div class="panel-head"><h2>Transferencia entre contas</h2></div>
    <form method="POST" action="{{ route('financeiro.contas.transfer') }}" class="form-grid">
        @csrf

        <label>
            Origem *
            <select name="origem" required>
                <option value="">Selecione a origem</option>
                @foreach ($contasAtivas as $contaTransferencia)
                    <option value="{{ $contaTransferencia->id }}" @selected(old('origem') == $contaTransferencia->id)>
                        {{ $contaTransferencia->nome }} - R$ {{ number_format($contaTransferencia->saldo_atual, 2, ',', '.') }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Destino *
            <select name="destino" required>
                <option value="">Selecione o destino</option>
                @foreach ($contasAtivas as $contaTransferencia)
                    <option value="{{ $contaTransferencia->id }}" @selected(old('destino') == $contaTransferencia->id)>
                        {{ $contaTransferencia->nome }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Valor *
            <input name="valor" value="{{ old('valor', '0,00') }}" inputmode="decimal" required>
        </label>

        <label>
            Data
            <input type="date" name="data_transferencia" value="{{ old('data_transferencia', date('Y-m-d')) }}">
        </label>

        <label class="span-2">
            Descricao
            <input name="descricao" value="{{ old('descricao') }}" maxlength="255" placeholder="Transferencia entre contas">
        </label>

        <div class="form-actions span-2">
            <button class="btn primary" type="submit">Registrar transferencia</button>
        </div>
    </form>
</section>
