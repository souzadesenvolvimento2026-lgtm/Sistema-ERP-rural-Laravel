<section class="panel">
    <form method="GET" action="{{ route('talhoes.chuva.index') }}" class="form-grid panel-body">
        <label>
            Ano
            <input type="number" name="ano" value="{{ $ano }}" min="2000" max="2100">
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>
</section>
