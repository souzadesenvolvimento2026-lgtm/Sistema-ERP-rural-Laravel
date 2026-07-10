<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('financeiro.receitas.index') }}" class="form-grid panel-body">
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
            De
            <input type="date" name="date_from" value="{{ $filtros['date_from'] }}">
        </label>

        <label>
            Ate
            <input type="date" name="date_to" value="{{ $filtros['date_to'] }}">
        </label>

        <label>
            Buscar
            <input name="search" value="{{ $filtros['search'] }}" placeholder="Descricao, comprador ou produtor">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('financeiro.receitas.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
