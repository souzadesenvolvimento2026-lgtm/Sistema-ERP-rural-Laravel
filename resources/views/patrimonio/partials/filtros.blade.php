<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('patrimonio.index') }}" class="form-grid panel-body">
        <label>
            Status
            <select name="status">
                <option value="ativos" @selected($filtros['status'] === 'ativos')>Ativos</option>
                <option value="inativos" @selected($filtros['status'] === 'inativos')>Inativos</option>
                <option value="todos" @selected($filtros['status'] === 'todos')>Todos</option>
            </select>
        </label>

        <label>
            Tipo
            <select name="tipo">
                <option value="">Todos</option>
                @foreach ($tipos as $value => $label)
                    <option value="{{ $value }}" @selected($filtros['tipo'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Buscar
            <input name="search" value="{{ $filtros['search'] }}" placeholder="Nome, modelo, placa ou fornecedor">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('patrimonio.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
