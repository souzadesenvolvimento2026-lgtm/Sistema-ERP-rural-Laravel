<section class="panel">
    <div class="panel-head"><h2>Novo registro de chuva</h2></div>
    <form method="POST" action="{{ route('talhoes.chuva.store') }}" class="form-grid">
        @csrf

        <label>
            Data *
            <input type="date" name="data_chuva" value="{{ old('data_chuva', now()->format('Y-m-d')) }}" required>
        </label>

        <label>
            Volume (mm) *
            <input name="volume_mm" value="{{ old('volume_mm') }}" required inputmode="decimal" placeholder="0,00">
        </label>

        <label>
            Talhão
            <select name="talhao_id">
                <option value="">Fazenda geral</option>
                @foreach ($talhoes as $talhao)
                    <option value="{{ $talhao->id }}" @selected((string)old('talhao_id') === (string)$talhao->id)>{{ $talhao->nome }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Fonte *
            <select name="fonte" required>
                <option value="manual" @selected(old('fonte', 'manual') === 'manual')>Manual</option>
                <option value="pluviometro" @selected(old('fonte') === 'pluviometro')>Pluviômetro</option>
                <option value="estacao" @selected(old('fonte') === 'estacao')>Estação</option>
                <option value="importado" @selected(old('fonte') === 'importado')>Importado</option>
            </select>
        </label>

        <label class="span-2">
            Observações
            <textarea name="observacoes" rows="3">{{ old('observacoes') }}</textarea>
        </label>

        <div class="form-actions">
            <button class="btn primary" type="submit">Salvar chuva</button>
        </div>
    </form>
</section>
