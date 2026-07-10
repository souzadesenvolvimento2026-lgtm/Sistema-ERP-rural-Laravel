<section class="panel">
    <form method="GET" action="{{ route('talhoes.atividades.index') }}" class="form-grid panel-body">
        <label>
            Safra
            <select name="safra_id">
                <option value="">Todas</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected($filtroSafraId == $safra->id)>{{ $safra->descricao }}</option>
                @endforeach
            </select>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
