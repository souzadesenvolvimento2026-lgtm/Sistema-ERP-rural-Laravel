<section class="panel">
    <div class="panel-head"><h2>{{ isset($conta) ? 'Editar conta' : 'Nova conta' }}</h2></div>
    <form method="POST" action="{{ isset($conta) ? route('financeiro.contas.update', $conta->id, false) : route('financeiro.contas.store', [], false) }}" class="form-grid">
        @csrf
        @isset($conta)
            @method('PUT')
        @endisset

        <label>
            Nome *
            <input name="nome" value="{{ old('nome', $conta->nome ?? '') }}" required maxlength="100" placeholder="Banco, caixa ou investimento">
        </label>

        <label>
            Tipo *
            <select name="tipo" required>
                @foreach ([
                    'conta_corrente' => 'Conta corrente',
                    'conta_poupanca' => 'Conta poupança',
                    'caixa_interno' => 'Caixa interno',
                    'investimento' => 'Investimento',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('tipo', $conta->tipo ?? 'conta_corrente') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Banco
            <input name="banco" value="{{ old('banco', $conta->banco ?? '') }}" maxlength="80">
        </label>

        <label>
            Agência
            <input name="agencia" value="{{ old('agencia', $conta->agencia ?? '') }}" maxlength="20">
        </label>

        <label>
            Conta
            <input name="numero_conta" value="{{ old('numero_conta', $conta->numero_conta ?? '') }}" maxlength="30">
        </label>

        <label>
            Saldo inicial
            <input name="saldo_inicial" value="{{ old('saldo_inicial', isset($conta) ? number_format($conta->saldo_inicial, 2, ',', '.') : '0,00') }}" inputmode="decimal">
        </label>

        <div class="form-actions span-2">
            @isset($conta)
                <a class="btn" href="{{ route('financeiro.contas.index', [], false) }}">Cancelar</a>
            @endisset
            <button class="btn primary" type="submit">{{ isset($conta) ? 'Atualizar conta' : 'Salvar conta' }}</button>
        </div>
    </form>
</section>
