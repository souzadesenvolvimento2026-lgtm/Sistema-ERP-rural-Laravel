@php
    $selecionados = collect(old('talhoes', $safra->talhoes ?? []))->map(fn ($id) => (int)$id)->all();
@endphp

<section class="panel">
    <div class="panel-head"><h2>Dados da safra</h2></div>
    <div class="panel-body">
        <div class="form-grid">
            <div class="field wide">
                <label>Descrição</label>
                <input name="descricao" value="{{ old('descricao', $safra->descricao ?? '') }}" required>
            </div>
            <div class="field">
                <label>Cultura</label>
                <select name="cultura_id">
                    <option value="">Não informada</option>
                    @foreach ($culturas as $cultura)
                        <option value="{{ $cultura->id }}" @selected((string)old('cultura_id', $safra->cultura_id ?? '') === (string)$cultura->id)>{{ $cultura->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Referência</label>
                <select name="safra_referencia" required>
                    @foreach (['primeira' => 'Primeira safra', 'segunda' => 'Segunda safra', 'terceira' => 'Terceira safra'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('safra_referencia', $safra->safra_referencia ?? 'primeira') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status" required>
                    @foreach (['planejamento' => 'Planejamento', 'em_andamento' => 'Em andamento', 'colhida' => 'Colhida', 'encerrada' => 'Encerrada'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $safra->status ?? 'planejamento') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Início</label>
                <input type="date" name="data_inicio" value="{{ old('data_inicio', $safra->data_inicio ?? date('Y-m-d')) }}" required>
            </div>
            <div class="field">
                <label>Fim</label>
                <input type="date" name="data_fim" value="{{ old('data_fim', $safra->data_fim ?? '') }}">
            </div>
            <div class="field">
                <label>Área plantada</label>
                <input name="area_plantada" inputmode="decimal" value="{{ old('area_plantada', isset($safra) && $safra->area_plantada !== null ? number_format($safra->area_plantada, 2, ',', '.') : '') }}">
            </div>
            <div class="field">
                <label>Produção estimada</label>
                <input name="producao_estimada" inputmode="decimal" value="{{ old('producao_estimada', isset($safra) && $safra->producao_estimada !== null ? number_format($safra->producao_estimada, 2, ',', '.') : '') }}">
            </div>
            <div class="field">
                <label>Preço estimado</label>
                <input name="preco_estimado" inputmode="decimal" value="{{ old('preco_estimado', isset($safra) && $safra->preco_estimado !== null ? number_format($safra->preco_estimado, 2, ',', '.') : '') }}">
            </div>
            <div class="field full">
                <label>Talhões</label>
                <select name="talhoes[]" multiple size="6">
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao->id }}" @selected(in_array((int)$talhao->id, $selecionados, true))>{{ $talhao->nome }} - {{ number_format((float)$talhao->area, 2, ',', '.') }} ha</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <label>Observações</label>
                <textarea name="observacoes">{{ old('observacoes', $safra->observacoes ?? '') }}</textarea>
            </div>
        </div>
    </div>
</section>
