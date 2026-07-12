@extends('layouts.farmfort', ['title' => 'FarmFort - Fluxo de Caixa'])

@php
    use App\Support\FarmFormat;
@endphp

@section('content')
    <style>
        .ff-fluxo-filter-card .form-label { font-size:12px; color:var(--ff-muted); text-transform:uppercase; letter-spacing:0; font-weight:900; }
        .ff-fluxo-safras-dropdown .dropdown-menu { min-width:320px; max-height:320px; overflow:auto; border-radius:8px; }
        .ff-fluxo-safra-check { display:flex; align-items:center; gap:9px; border:1px solid var(--ff-border); border-radius:8px; padding:10px 11px; background:var(--ff-surface); font-weight:700; margin-bottom:8px; cursor:pointer; }
        .ff-fluxo-safra-check input { accent-color:#179b6b; }
        .ff-fluxo-safra-check small { display:block; color:var(--ff-muted); font-weight:600; margin-top:2px; }
        .ff-fluxo-safra-check.ff-fluxo-select-all { background:rgba(47,200,155,.08); border-color:rgba(47,200,155,.45); }
        .ff-fluxo-context-note { border:1px solid rgba(47,200,155,.18); background:rgba(47,200,155,.06); border-radius:8px; padding:12px 14px; }
        .ff-fluxo-chart-card .card-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .ff-fluxo-chart-card .card-body { height:340px; }
        .ff-fluxo-chart-card canvas { width:100% !important; height:100% !important; }
        .ff-fluxo-expand-btn { width:38px; height:34px; display:inline-flex; align-items:center; justify-content:center; padding:0; }
        .ff-fluxo-focus-modal .modal-dialog { max-width:none; margin:0; }
        .ff-fluxo-focus-modal .modal-content { min-height:100vh; border:0; border-radius:0; background:var(--ff-bg); }
        .ff-fluxo-focus-modal .modal-header { min-height:64px; border-bottom:1px solid var(--ff-border); }
        .ff-fluxo-focus-modal .modal-body { height:calc(100vh - 64px); padding:18px 22px 24px; }
        .ff-fluxo-focus-chart-wrap { height:100%; min-height:420px; }
        .ff-fluxo-focus-chart-wrap canvas { width:100% !important; height:100% !important; }
    </style>

    <form class="card ff-fluxo-filter-card mb-4" method="get" id="fluxoFiltros">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label">Fazenda</label>
                    <select class="form-select" disabled>
                        <option selected>{{ $propertyName }}</option>
                    </select>
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="form-label">Safras</label>
                    <div class="dropdown ff-fluxo-safras-dropdown">
                        <button class="form-select text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            {{ $safraButton ?: 'Todas as safras' }}
                        </button>
                        <div class="dropdown-menu p-3">
                            <div class="text-muted small mb-2">Marcar safra limpa o período e analisa apenas a(s) safra(s) escolhida(s).</div>
                            <label class="ff-fluxo-safra-check ff-fluxo-select-all">
                                <input type="checkbox" id="fluxoSelecionarTodasSafras" @checked($allSafrasSelected || $selectedSafraIds === [])>
                                <span>
                                    Todas as safras
                                    <small>Seleciona todos os anos e safras cadastradas nesta fazenda.</small>
                                </span>
                            </label>
                            @foreach ($safras as $safra)
                                <label class="ff-fluxo-safra-check">
                                    <input type="checkbox" name="safras[]" value="{{ $safra->id }}" @checked($selectedSafraIds === [] || in_array((int) $safra->id, $selectedSafraIds, true))>
                                    <span>{{ $safra->descricao }}</span>
                                </label>
                            @endforeach
                            <button class="btn btn-sm btn-farmflow w-100 mt-2" type="submit">Aplicar safras</button>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-md-8">
                    <label class="form-label">Período</label>
                    <div class="d-flex gap-2">
                        <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}" data-fluxo-period>
                        <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}" data-fluxo-period>
                    </div>
                    <div class="text-muted small mt-1">Informar período desmarca as safras.</div>
                </div>

                <div class="col-12 d-flex flex-wrap justify-content-between gap-2">
                    <div class="ff-fluxo-context-note text-muted small">
                        <strong>Análise por safra:</strong> {{ $contextoSafras }}
                        <span class="ms-2">Fazenda: <strong>{{ $propertyName }}</strong></span>
                    </div>
                    <button class="btn btn-farmflow"><i class="bi bi-search me-1"></i> Atualizar fluxo</button>
                </div>
            </div>
        </div>
    </form>

    <section class="stats">
        <article class="stat success"><span>Receitas previstas</span><strong>{{ FarmFormat::money($totais['receitas']) }}</strong></article>
        <article class="stat danger"><span>Despesas previstas</span><strong>{{ FarmFormat::money($totais['despesas']) }}</strong></article>
        <article class="stat success"><span>Total Recebido (real)</span><strong>{{ FarmFormat::money($totais['recebido']) }}</strong></article>
        <article class="stat danger"><span>Total Pago (real)</span><strong>{{ FarmFormat::money($totais['pago']) }}</strong></article>
    </section>

    <div class="card mb-4 ff-fluxo-chart-card">
        <div class="card-header">
            <span><i class="bi bi-graph-up-arrow me-2"></i>Gráfico do fluxo de caixa - {{ $contextoSafras }}</span>
            <button type="button" class="btn btn-outline-secondary btn-sm ff-fluxo-expand-btn" data-bs-toggle="modal" data-bs-target="#modalFluxoGraficoAmpliado" title="Ampliar gráfico" aria-label="Ampliar gráfico">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
        </div>
        <div class="card-body">
            <canvas id="chartFluxo"></canvas>
        </div>
    </div>

    <div class="modal fade ff-fluxo-focus-modal" id="modalFluxoGraficoAmpliado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Fluxo de caixa ampliado</h5>
                        <small class="text-muted">{{ $contextoSafras }} | Mês</small>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left me-1"></i> Voltar
                    </button>
                </div>
                <div class="modal-body">
                    <div class="ff-fluxo-focus-chart-wrap">
                        <canvas id="chartFluxoAmpliado"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head">
            <h2><i class="bi bi-table me-2"></i>Detalhamento do gráfico</h2>
            <span class="badge text-bg-secondary">Por Mês</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Mês</th>
                    <th class="text-success">Receitas previstas</th>
                    <th class="text-danger">Despesas previstas</th>
                    <th>Saldo Previsto</th>
                    <th class="text-success">Recebido</th>
                    <th class="text-danger">Pago</th>
                    <th>Saldo Real</th>
                    <th>Acumulado</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><strong>{{ $row->mes_label }}</strong></td>
                        <td class="text-success">{{ $row->receitas }}</td>
                        <td class="text-danger">{{ $row->despesas }}</td>
                        <td class="{{ $row->saldo_previsto_classe }}">{{ $row->saldo_previsto }}</td>
                        <td class="text-success">{{ $row->recebido }}</td>
                        <td class="text-danger">{{ $row->pago }}</td>
                        <td class="fw-bold {{ $row->saldo_realizado_classe }}">{{ $row->saldo_realizado }}</td>
                        <td class="{{ $row->acumulado_classe }}">{{ $row->acumulado }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td>TOTAL</td>
                    <td class="text-success">{{ FarmFormat::money($totais['receitas']) }}</td>
                    <td class="text-danger">{{ FarmFormat::money($totais['despesas']) }}</td>
                    <td class="{{ $totais['saldo_previsto_classe'] }}">{{ FarmFormat::money($totais['saldo_previsto']) }}</td>
                    <td class="text-success">{{ FarmFormat::money($totais['recebido']) }}</td>
                    <td class="text-danger">{{ FarmFormat::money($totais['pago']) }}</td>
                    <td class="{{ $totais['saldo_realizado_classe'] }}">{{ FarmFormat::money($totais['saldo_realizado']) }}</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.querySelectorAll('[data-fluxo-auto]').forEach((el) => {
            el.addEventListener('change', () => document.getElementById('fluxoFiltros')?.submit());
        });

        const fluxoSafraChecks = Array.from(document.querySelectorAll('input[name="safras[]"]'));
        const fluxoSelectAllSafras = document.getElementById('fluxoSelecionarTodasSafras');
        function fluxoLimparPeriodo() {
            document.querySelectorAll('[data-fluxo-period]').forEach((input) => { input.value = ''; });
        }
        function fluxoAtualizarSelectAllSafras() {
            if (!fluxoSelectAllSafras) return;
            fluxoSelectAllSafras.checked = fluxoSafraChecks.length > 0 && fluxoSafraChecks.every((check) => check.checked);
        }
        fluxoSelectAllSafras?.addEventListener('change', () => {
            fluxoSafraChecks.forEach((check) => { check.checked = fluxoSelectAllSafras.checked; });
            if (fluxoSelectAllSafras.checked) fluxoLimparPeriodo();
        });
        fluxoSafraChecks.forEach((check) => {
            check.addEventListener('change', () => {
                if (check.checked) fluxoLimparPeriodo();
                fluxoAtualizarSelectAllSafras();
            });
        });
        document.querySelectorAll('[data-fluxo-period]').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.value) {
                    fluxoSafraChecks.forEach((check) => { check.checked = false; });
                    if (fluxoSelectAllSafras) fluxoSelectAllSafras.checked = false;
                }
            });
        });

        const fluxoChartData = {
            labels: @json($chart['labels']),
            datasets: [
                { label: 'Receitas', data: @json($chart['receitas']), backgroundColor: 'rgba(26,107,60,.65)', order: 2 },
                { label: 'Despesas', data: @json($chart['despesas']), backgroundColor: 'rgba(220,53,69,.65)', order: 2 },
                {
                    label: 'Acumulado',
                    data: @json($chart['acumulado']),
                    type: 'line',
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,.08)',
                    fill: true,
                    tension: .4,
                    order: 1,
                    pointBackgroundColor: '#0d6efd'
                }
            ]
        };
        const fluxoChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index' },
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 60, minRotation: 0 } },
                y: { ticks: { callback: v => window.ffMoneyBR ? window.ffMoneyBR(v) : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0)) } }
            }
        };
        function fluxoCreateChart(canvas) {
            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: [...fluxoChartData.labels],
                    datasets: fluxoChartData.datasets.map(dataset => ({ ...dataset, data: [...dataset.data] }))
                },
                options: fluxoChartOptions
            });
        }
        const fluxoCanvas = document.getElementById('chartFluxo');
        if (fluxoCanvas && window.Chart) fluxoCreateChart(fluxoCanvas);

        let fluxoChartAmpliado = null;
        const fluxoModal = document.getElementById('modalFluxoGraficoAmpliado');
        const fluxoCanvasAmpliado = document.getElementById('chartFluxoAmpliado');
        if (fluxoModal && fluxoCanvasAmpliado) {
            fluxoModal.addEventListener('shown.bs.modal', () => {
                if (fluxoChartAmpliado) fluxoChartAmpliado.destroy();
                fluxoChartAmpliado = fluxoCreateChart(fluxoCanvasAmpliado);
                fluxoChartAmpliado.resize();
            });
            fluxoModal.addEventListener('hidden.bs.modal', () => {
                if (fluxoChartAmpliado) {
                    fluxoChartAmpliado.destroy();
                    fluxoChartAmpliado = null;
                }
            });
        }
    </script>
@endpush
