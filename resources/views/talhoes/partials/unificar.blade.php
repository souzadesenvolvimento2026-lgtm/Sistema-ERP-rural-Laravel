<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Unificar talhoes</h2>
            <p class="subtitle">Move historico, despesas e vinculos dos talhoes de origem para o destino.</p>
        </div>
    </div>

    @if ($talhoesAtivos->count() >= 2)
        <form method="POST" action="{{ route('talhoes.unificar') }}" class="form-grid">
            @csrf

            <label class="field">
                <span>Talhao destino</span>
                <select name="talhao_destino_id" required>
                    <option value="">Selecione</option>
                    @foreach ($talhoesAtivos as $talhao)
                        <option value="{{ $talhao->id }}">{{ $talhao->nome }} - {{ $talhao->area }}</option>
                    @endforeach
                </select>
            </label>

            <div class="field full">
                <span>Talhoes de origem</span>
                <div class="option-grid">
                    @foreach ($talhoesAtivos as $talhao)
                        <label class="check-row">
                            <input type="checkbox" name="talhoes_origem[]" value="{{ $talhao->id }}">
                            <span>{{ $talhao->nome }} - {{ $talhao->area }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="check-row full">
                <input type="checkbox" name="somar_area" value="1" checked>
                <span>Somar a area dos talhoes de origem no destino</span>
            </label>

            <div class="actions full">
                <button type="submit" class="btn primary">Unificar talhoes</button>
            </div>
        </form>
    @else
        <p class="muted">Cadastre pelo menos dois talhoes ativos para usar a unificacao.</p>
    @endif
</section>
