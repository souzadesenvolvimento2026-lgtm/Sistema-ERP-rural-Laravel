@php
    $safra = $safra ?? null;
    $safraIdAtual = (int) ($safra->id ?? 0);
    $selecionados = collect(old('talhoes', $safra->talhoes ?? []))
        ->map(fn ($talhaoId) => (int) $talhaoId)
        ->all();
    $statusAtual = old('status', $safra->status ?? 'planejamento');
    $dataInicio = old('data_inicio', isset($safra->data_inicio) ? substr((string) $safra->data_inicio, 0, 10) : now()->toDateString());
    $dataFim = old('data_fim', isset($safra->data_fim) ? substr((string) $safra->data_fim, 0, 10) : '');
    $areaPlantada = old('area_plantada', isset($safra->area_plantada) && $safra->area_plantada !== null ? number_format((float) $safra->area_plantada, 2, '.', '') : '');
    $producaoEstimada = old('producao_estimada', isset($safra->producao_estimada) && $safra->producao_estimada !== null ? number_format((float) $safra->producao_estimada, 2, '.', '') : '');
    $precoEstimado = old('preco_estimado', isset($safra->preco_estimado) && $safra->preco_estimado !== null ? number_format((float) $safra->preco_estimado, 2, ',', '.') : '');
@endphp

<div class="ff-safra-form-grid" data-safra-form-fields>
    <label class="ff-safra-field ff-safra-field--wide">
        <span>Descrição *</span>
        <input name="descricao" value="{{ old('descricao', $safra->descricao ?? '') }}" required maxlength="120" placeholder="Ex: Soja 2025/26">
    </label>

    <label class="ff-safra-field">
        <span>Cultura</span>
        <select name="cultura_id">
            <option value="">Selecione</option>
            @foreach ($culturas as $cultura)
                <option value="{{ $cultura->id }}" @selected((string) old('cultura_id', $safra->cultura_id ?? '') === (string) $cultura->id)>
                    {{ $cultura->nome }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="ff-safra-field">
        <span>Safra de Referência *</span>
        <select name="safra_referencia" required>
            <option value="">Selecione</option>
            @foreach (['primeira' => 'Primeira Safra', 'segunda' => 'Segunda Safra', 'terceira' => 'Terceira Safra'] as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(old('safra_referencia', $safra->safra_referencia ?? '') === $valor)>
                    {{ $rotulo }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="ff-safra-field">
        <span>Status</span>
        <select name="status" required data-safra-status>
            @foreach (['planejamento' => 'Planejamento', 'em_andamento' => 'Em Andamento', 'colhida' => 'Colhida', 'encerrada' => 'Arquivada / Encerrada'] as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($statusAtual === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </label>

    <label class="ff-safra-field">
        <span>Data Início *</span>
        <input type="date" name="data_inicio" value="{{ $dataInicio }}" required>
    </label>

    <label class="ff-safra-field">
        <span>Data Fim</span>
        <input type="date" name="data_fim" value="{{ $dataFim }}">
    </label>

    <label class="ff-safra-field">
        <span>Área Plantada (ha)</span>
        <input type="number" name="area_plantada" value="{{ $areaPlantada }}" step="0.01" min="0" placeholder="0.00" data-safra-area>
    </label>

    <label class="ff-safra-field">
        <span>Produtividade estimada (sc/ha)</span>
        <input type="number" name="producao_estimada" value="{{ $producaoEstimada }}" step="0.01" min="0" placeholder="0,00">
    </label>

    <label class="ff-safra-field">
        <span>Preço Estimado (R$/sc)</span>
        <input name="preco_estimado" value="{{ $precoEstimado }}" inputmode="decimal" placeholder="0,00" class="moeda">
    </label>

    <div class="ff-safra-field ff-safra-field--full">
        <div class="ff-safra-talhao-panel">
            <div class="ff-safra-talhao-head">
                <div>
                    <label class="form-label mb-1">Talhões da cultura</label>
                    <p class="ff-safra-help">
                        Selecione os talhões usados nesta cultura. O planejamento futuro é permitido; a execução só é bloqueada se outro cultivo ainda estiver em campo.
                    </p>
                </div>
                <label class="ff-safra-select-all">
                    <input type="checkbox" data-safra-select-all>
                    <span>Selecionar todos disponíveis</span>
                </label>
            </div>

            @if ($talhoes->isEmpty())
                <div class="ff-safra-talhao-empty">Nenhum talhão ativo cadastrado nesta propriedade.</div>
            @else
                <div class="ff-safra-talhao-list">
                    @foreach ($talhoes as $talhao)
                        @php
                            $usos = collect($talhao->usos ?? [])->reject(fn ($uso) => (int) $uso->safra_id === $safraIdAtual);
                            $bloqueiaExecucao = $usos->contains(fn ($uso) => $uso->status === 'em_andamento' && ! $uso->colhido);
                            $boBloqueado = $statusAtual === 'em_andamento' && $bloqueiaExecucao;
                            $boSelecionado = in_array((int) $talhao->id, $selecionados, true) && ! $boBloqueado;
                        @endphp
                        <label class="ff-safra-talhao-row {{ $boBloqueado ? 'is-blocked' : '' }}">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="talhoes[]"
                                value="{{ $talhao->id }}"
                                data-safra-talhao-check
                                data-area="{{ (float) $talhao->area }}"
                                data-blocks-execution="{{ $bloqueiaExecucao ? '1' : '0' }}"
                                @checked($boSelecionado)
                                @disabled($boBloqueado)
                            >
                            <span class="ff-safra-talhao-info">
                                <strong>{{ $talhao->nome }}</strong>
                                <small>{{ number_format((float) $talhao->area, 2, ',', '.') }} ha</small>
                                @if ($usos->isNotEmpty())
                                    <em>
                                        @foreach ($usos as $uso)
                                            @php
                                                $nomeUso = trim(implode(' - ', array_filter([$uso->cultura_nome, $uso->safra_nome])));
                                            @endphp
                                            @if ($uso->status === 'em_andamento' && ! $uso->colhido)
                                                <span>Em campo: {{ $nomeUso }}. Pode planejar; iniciar fica bloqueado até registrar a colheita.</span>
                                            @elseif ($uso->status === 'planejamento')
                                                <span>Também planejado em: {{ $nomeUso }}.</span>
                                            @else
                                                <span>Colheita registrada em: {{ $nomeUso }}.</span>
                                            @endif
                                        @endforeach
                                    </em>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <label class="ff-safra-field ff-safra-field--full">
        <span>Observações</span>
        <textarea name="observacoes" rows="3">{{ old('observacoes', $safra->observacoes ?? '') }}</textarea>
    </label>
</div>
