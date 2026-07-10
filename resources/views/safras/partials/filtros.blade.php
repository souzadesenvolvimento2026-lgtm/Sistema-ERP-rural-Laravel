<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('safras.index') }}" class="form-grid panel-body">
        <label>
            Status
            <select name="status">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filtros['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Buscar
            <input name="search" value="{{ $filtros['search'] }}" placeholder="Descricao, cultura ou observacao">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('safras.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
