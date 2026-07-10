<section class="panel">
    <form method="GET" action="{{ route('relatorios.kpis') }}" class="form-grid panel-body">
        <label>
            Safra
            <select name="safra_id">
                @foreach ($safras as $item)
                    <option value="{{ $item->id }}" @selected($safraId == $item->id)>{{ $item->descricao }}</option>
                @endforeach
            </select>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
