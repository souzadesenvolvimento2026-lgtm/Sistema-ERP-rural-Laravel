<form method="GET" action="{{ route('relatorios.comparativo-safras.index') }}" class="ff-comparison-filter-form">
    <label class="ff-comparison-filter-field">
        <span>Fazenda</span>
        <select name="fazenda_id" onchange="this.form.submit()">
            @foreach ($fazendas as $fazenda)
                <option value="{{ $fazenda->id }}" @selected($propertyId === $fazenda->id)>{{ $fazenda->nome }}</option>
            @endforeach
        </select>
    </label>

    <label class="ff-comparison-filter-field">
        <span>Safra de referência</span>
        <select name="ciclo_safra" onchange="this.form.submit()">
            <option value="" @selected($ciclo === '')>Todas</option>
            <option value="primeira" @selected($ciclo === 'primeira')>1ª Safra</option>
            <option value="segunda" @selected($ciclo === 'segunda')>2ª Safra</option>
            <option value="terceira" @selected($ciclo === 'terceira')>3ª Safra</option>
        </select>
    </label>

    <label class="ff-comparison-filter-field">
        <span>Visualização</span>
        <select name="modo" onchange="this.form.submit()">
            <option value="reais" @selected($modo === 'reais')>Reais por hectare</option>
            <option value="sacas_ha" @selected($modo === 'sacas_ha')>Sacas por hectare</option>
        </select>
    </label>

    <noscript>
        <button class="btn primary" type="submit">Aplicar filtros</button>
    </noscript>
</form>
