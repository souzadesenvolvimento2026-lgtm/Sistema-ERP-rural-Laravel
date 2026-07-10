<section class="panel">
    <div class="panel-head"><h2>Filtros</h2></div>
    <form method="GET" action="{{ route('colheita.index') }}" class="form-grid panel-body">
        <label>
            Safra
            <select name="safra_id">
                <option value="">Todas</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected($filtros['safra_id'] === $safra->id)>{{ $safra->descricao }}{{ $safra->cultura_nome ? ' - '.$safra->cultura_nome : '' }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Talhao
            <select name="talhao_id">
                <option value="">Todos</option>
                @foreach ($talhoes as $talhao)
                    <option value="{{ $talhao->id }}" @selected($filtros['talhao_id'] === $talhao->id)>{{ $talhao->nome }}</option>
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
            <input name="search" value="{{ $filtros['search'] }}" placeholder="Ticket, motorista, placa ou destino">
        </label>

        <div class="form-actions">
            <a class="btn" href="{{ route('colheita.index') }}">Limpar</a>
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
