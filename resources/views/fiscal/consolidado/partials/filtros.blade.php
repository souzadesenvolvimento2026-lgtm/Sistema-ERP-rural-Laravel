<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ url()->current() }}" class="form-grid panel-body">
        <label>
            Status
            <select name="status">
                <option value="">Todos</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filtros['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            De
            <input type="date" name="date_from" value="{{ $filtros['date_from'] }}">
        </label>

        <label>
            Até
            <input type="date" name="date_to" value="{{ $filtros['date_to'] }}">
        </label>

        <label>
            Fornecedor/emitente
            <input name="supplier" value="{{ $filtros['supplier'] }}" placeholder="Nome ou CNPJ">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ url()->current() }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
