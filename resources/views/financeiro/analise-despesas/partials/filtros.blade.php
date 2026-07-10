<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('financeiro.analise-despesas.index') }}" class="form-grid panel-body">
        <label>
            Ano
            <select name="fd_ano">
                @foreach ($anos as $ano)
                    <option value="{{ $ano }}" @selected($filtros['ano'] === $ano)>{{ $ano }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Mes
            <select name="fd_mes">
                <option value="todos" @selected(!$filtros['mes'])>Todos</option>
                @foreach ($meses as $numero => $nome)
                    <option value="{{ $numero }}" @selected($filtros['mes'] === $numero)>{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Safra
            <select name="fd_safra">
                <option value="todos" @selected(!$filtros['safra_id'])>Todas</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected($filtros['safra_id'] === $safra->id)>{{ $safra->descricao }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Categoria
            <select name="fd_tipo">
                <option value="todos" @selected($filtros['tipo'] === 'todos')>Todas</option>
                @foreach ($tipos as $tipo => $label)
                    <option value="{{ $tipo }}" @selected($filtros['tipo'] === $tipo)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Subcategoria
            <select name="fd_categoria">
                <option value="todos" @selected(!$filtros['categoria_id'])>Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($filtros['categoria_id'] === $categoria->id)>{{ $categoria->nome }}</option>
                @endforeach
            </select>
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('financeiro.analise-despesas.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
