<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('propriedades.index') }}" class="form-grid panel-body">
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
            <input name="search" value="{{ $filtros['search'] }}" placeholder="Nome, cidade, UF, responsavel ou documento">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('propriedades.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
