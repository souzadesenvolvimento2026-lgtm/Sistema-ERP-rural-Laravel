<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('relatorios.comparativo-safras.index') }}" class="form-grid panel-body">
        <label>
            Fazenda
            <select name="fazenda_id">
                @foreach ($fazendas as $fazenda)
                    <option value="{{ $fazenda->id }}" @selected($propertyId === $fazenda->id)>{{ $fazenda->nome }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Safra de referencia
            <select name="ciclo_safra">
                <option value="" @selected($ciclo === '')>Todas</option>
                <option value="primeira" @selected($ciclo === 'primeira')>1a Safra</option>
                <option value="segunda" @selected($ciclo === 'segunda')>2a Safra</option>
                <option value="terceira" @selected($ciclo === 'terceira')>3a Safra</option>
            </select>
        </label>

        <label>
            Visualizacao
            <select name="modo">
                <option value="reais" @selected($modo === 'reais')>Reais por hectare</option>
                <option value="sacas_ha" @selected($modo === 'sacas_ha')>Sacas por hectare</option>
            </select>
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('relatorios.comparativo-safras.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
