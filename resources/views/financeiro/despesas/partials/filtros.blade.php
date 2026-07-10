<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('financeiro.despesas.index') }}" class="form-grid panel-body">
        <label>
            Status
            <select name="status">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filtros['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Aprovacao
            <select name="aprovacao">
                @foreach ($aprovacaoOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filtros['aprovacao'] === $value)>{{ $label }}</option>
                @endforeach
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
            Categoria
            <select name="categoria_id">
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($filtros['categoria_id'] === $categoria->id)>{{ $categoria->nome }}{{ $categoria->tipo ? ' - '.$categoria->tipo : '' }}</option>
                @endforeach
            </select>
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
            De
            <input type="date" name="date_from" value="{{ $filtros['date_from'] }}">
        </label>

        <label>
            Ate
            <input type="date" name="date_to" value="{{ $filtros['date_to'] }}">
        </label>

        <label>
            Buscar
            <input name="search" value="{{ $filtros['search'] }}" placeholder="Descricao, fornecedor ou NF">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('financeiro.despesas.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
