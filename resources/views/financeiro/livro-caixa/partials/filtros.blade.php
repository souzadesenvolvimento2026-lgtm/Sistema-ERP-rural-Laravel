<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('financeiro.livro-caixa.index') }}" class="form-grid panel-body">
        <label>
            Ano
            <input type="number" name="ano" value="{{ $filtros['ano'] }}" min="2000" max="2100">
        </label>

        <label>
            Mês
            <select name="mes">
                <option value="todos">Todos</option>
                @foreach ($meses as $numero => $nome)
                    <option value="{{ $numero }}" @selected($filtros['mes'] === $numero)>{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Tipo
            <select name="tipo">
                <option value="todos" @selected($filtros['tipo'] === 'todos')>Entradas e saídas</option>
                <option value="entrada" @selected($filtros['tipo'] === 'entrada')>Somente entradas</option>
                <option value="saida" @selected($filtros['tipo'] === 'saida')>Somente saídas</option>
            </select>
        </label>

        <label>
            Safra
            <select name="safra_id">
                <option value="">Todas</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected($filtros['safra_id'] === $safra->id)>{{ $safra->descricao }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Conta/Banco
            <select name="conta_id">
                <option value="">Todas</option>
                @foreach ($contas as $conta)
                    <option value="{{ $conta->id }}" @selected($filtros['conta_id'] === $conta->id)>{{ $conta->nome }}{{ $conta->banco ? ' - '.$conta->banco : '' }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Categoria de despesa
            <select name="categoria_id">
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($filtros['categoria_id'] === $categoria->id)>{{ $categoria->nome }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Comprovante
            <select name="comprovante">
                <option value="todos" @selected($filtros['comprovante'] === 'todos')>Todos</option>
                <option value="com" @selected($filtros['comprovante'] === 'com')>Com arquivo</option>
                <option value="sem" @selected($filtros['comprovante'] === 'sem')>Sem arquivo</option>
            </select>
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('financeiro.livro-caixa.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn" href="{{ route('financeiro.livro-caixa.exportar', array_merge(request()->query(), ['formato' => 'csv'])) }}">CSV</a>
            <a class="btn" href="{{ route('financeiro.livro-caixa.exportar', array_merge(request()->query(), ['formato' => 'pdf'])) }}">PDF</a>
            <a class="btn" href="{{ route('financeiro.livro-caixa.exportar', array_merge(request()->query(), ['formato' => 'xls'])) }}">XLS</a>
        </div>
    </form>
</section>
