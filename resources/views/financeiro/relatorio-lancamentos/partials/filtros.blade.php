<section class="panel">
    <div class="panel-head">
        <h2>Filtros</h2>
        <span class="badge">{{ $periodo }}</span>
    </div>
    <form method="GET" action="{{ route('financeiro.relatorio-lancamentos.index') }}" class="form-grid panel-body">
        <label>
            Tipo
            <select name="filtro">
                <option value="todos" @selected($filtros['filtro'] === 'todos')>Todos</option>
                <option value="despesas" @selected($filtros['filtro'] === 'despesas')>Despesas</option>
                <option value="pagar" @selected($filtros['filtro'] === 'pagar')>Despesas a pagar</option>
                <option value="receitas" @selected($filtros['filtro'] === 'receitas')>Receitas</option>
                <option value="receber" @selected($filtros['filtro'] === 'receber')>Receitas a receber</option>
                <option value="transferencias" @selected($filtros['filtro'] === 'transferencias')>Transferencias</option>
            </select>
        </label>

        <label>
            Mes
            <input type="month" name="mes" value="{{ $filtros['mes'] }}">
        </label>

        <label>
            Data inicial
            <input type="date" name="data_inicio" value="{{ $filtros['data_inicio'] }}">
        </label>

        <label>
            Data final
            <input type="date" name="data_fim" value="{{ $filtros['data_fim'] }}">
        </label>

        <label>
            Conta
            <select name="conta_id">
                <option value="">Todas</option>
                @foreach ($contas as $conta)
                    <option value="{{ $conta->id }}" @selected($filtros['conta_id'] === $conta->id)>{{ $conta->nome }}{{ $conta->banco ? ' - '.$conta->banco : '' }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Todo periodo
            <select name="todos">
                <option value="0" @selected(!$filtros['todos'])>Nao</option>
                <option value="1" @selected($filtros['todos'])>Sim</option>
            </select>
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('financeiro.relatorio-lancamentos.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
