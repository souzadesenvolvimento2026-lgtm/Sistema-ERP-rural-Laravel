<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('fiscal.entrada-nf.index') }}" class="form-grid panel-body">
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
            Ate
            <input type="date" name="date_to" value="{{ $filtros['date_to'] }}">
        </label>

        <label>
            Fornecedor ou NF
            <input name="fornecedor" value="{{ $filtros['fornecedor'] }}" placeholder="Nome, CNPJ ou numero">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('fiscal.entrada-nf.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
