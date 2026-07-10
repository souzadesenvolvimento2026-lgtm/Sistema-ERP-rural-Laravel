@extends('layouts.farmfort', ['title' => 'FarmFort - DRE Gerencial'])

@php
    use App\Support\FarmFormat;

    $selectedSafras = collect($filtros['safra_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
    $allSelected = count($selectedSafras) > 0 && count($selectedSafras) === $safras->count();
    $safraButton = $allSelected || count($selectedSafras) === 0
        ? 'Todas as safras'
        : $safras->whereIn('id', $selectedSafras)->pluck('descricao')->implode(' + ');
    $percent = fn ($value) => ($totais['receitas'] ?? 0) > 0 ? number_format(((float) $value / (float) $totais['receitas']) * 100, 2, ',', '.') . '%' : '0,00%';
    $resultClass = ($totais['resultado'] ?? 0) >= 0 ? 'text-success' : 'text-danger';
    $resultLabel = ($totais['resultado'] ?? 0) >= 0 ? 'Lucro no período' : 'Prejuízo no período';
@endphp

@section('content')
    <style>
        .ff-dre-filter-card .form-label { font-size:12px; color:var(--ff-muted); text-transform:uppercase; font-weight:900; }
        .ff-dre-safras-dropdown .dropdown-menu { min-width:320px; max-height:320px; overflow:auto; border-radius:8px; }
        .ff-dre-safra-check { display:flex; align-items:center; gap:9px; border:1px solid var(--ff-border); border-radius:8px; padding:10px 11px; background:var(--ff-surface); font-weight:700; margin-bottom:8px; cursor:pointer; }
        .ff-dre-safra-check input { accent-color:#179b6b; }
        .ff-dre-safra-check small { display:block; color:var(--ff-muted); font-weight:600; margin-top:2px; }
        .ff-dre-select-all { background:rgba(47,200,155,.08); border-color:rgba(47,200,155,.45); }
        .ff-dre-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
        .ff-dre-kpi { position:relative; overflow:hidden; border:1px solid var(--ff-border); border-radius:8px; background:var(--ff-surface); padding:18px 18px 16px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
        .ff-dre-kpi::before { content:''; position:absolute; inset:0 auto 0 0; width:4px; background:var(--accent,#2fc89b); opacity:.95; }
        .ff-dre-kpi span { display:block; color:var(--ff-muted); font-size:12px; font-weight:900; text-transform:uppercase; }
        .ff-dre-kpi strong { display:block; margin-top:10px; font-size:24px; line-height:1.05; font-weight:900; }
        .ff-dre-kpi small { display:block; margin-top:8px; color:var(--ff-muted); font-weight:700; }
        .ff-dre-kpi.is-revenue { --accent:#119c66; }
        .ff-dre-kpi.is-cost { --accent:#d89422; }
        .ff-dre-kpi.is-expense { --accent:#dc3545; }
        .ff-dre-kpi.is-result { --accent:#0d6efd; }
        .ff-dre-kpi.is-margin { --accent:#7c3aed; }
        .ff-dre-exec-card { border:1px solid rgba(47,200,155,.18); box-shadow:0 1px 2px rgba(15,23,42,.05); }
        .ff-dre-exec-card .card-body { height:420px; }
        .ff-dre-exec-card canvas { width:100% !important; height:100% !important; }
        .ff-dre-result-pill { display:inline-flex; align-items:center; gap:7px; padding:7px 11px; border-radius:999px; background:var(--ff-accent-soft); color:var(--ff-accent-strong); font-weight:900; }
        .ff-dre-result-pill.loss { background:var(--ff-danger-soft); color:var(--ff-danger); }
        .ff-dre-summary-table th { font-size:12px; text-transform:uppercase; color:var(--ff-muted); }
        .ff-dre-summary-table .ff-dre-detail-row { display:none; }
        .ff-dre-summary-table .ff-dre-detail-row.show { display:table-row; }
        .ff-dre-detail-label { padding-left:28px !important; color:var(--ff-muted); }
        .ff-dre-toggle { border:1px solid var(--ff-border); background:var(--ff-surface-2); color:var(--ff-text); border-radius:999px; padding:4px 9px; font-weight:800; font-size:12px; }
        .ff-dre-mini-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
        .ff-dre-mini-summary span { display:block; color:var(--ff-muted); font-size:11px; text-transform:uppercase; font-weight:900; }
        .ff-dre-mini-summary strong { display:block; margin-top:4px; }
        @media (max-width: 1100px) { .ff-dre-kpis, .ff-dre-mini-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 640px) { .ff-dre-kpis, .ff-dre-mini-summary { grid-template-columns:1fr; } }
    </style>

    <form class="card ff-dre-filter-card mb-4" method="get" id="dreFiltros">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-xl-4 col-md-6">
                    <label class="form-label">Fazenda</label>
                    <select class="form-select" disabled>
                        <option selected>{{ $propertyName }}</option>
                    </select>
                </div>
                <div class="col-xl-4 col-md-6">
                    <label class="form-label">Safras</label>
                    <div class="dropdown ff-dre-safras-dropdown">
                        <button class="form-select text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            {{ $safraButton ?: 'Todas as safras' }}
                        </button>
                        <div class="dropdown-menu p-3">
                            <div class="text-muted small mb-2">Marcar safra limpa o período e analisa apenas a(s) safra(s) escolhida(s).</div>
                            <label class="ff-dre-safra-check ff-dre-select-all">
                                <input type="checkbox" id="dreSelecionarTodasSafras" @checked($allSelected || count($selectedSafras) === 0)>
                                <span>Todas as safras<small>Seleciona todos os anos e safras cadastradas nesta fazenda.</small></span>
                            </label>
                            @foreach ($safras as $safra)
                                <label class="ff-dre-safra-check">
                                    <input type="checkbox" name="safras[]" value="{{ $safra->id }}" @checked(count($selectedSafras) === 0 || in_array((int) $safra->id, $selectedSafras, true))>
                                    <span>{{ $safra->descricao }}</span>
                                </label>
                            @endforeach
                            <button class="btn btn-sm btn-farmflow w-100 mt-2" type="submit">Aplicar safras</button>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12">
                    <label class="form-label">Período</label>
                    <div class="d-flex gap-2">
                        <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}" data-dre-period>
                        <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}" data-dre-period>
                    </div>
                    <div class="text-muted small mt-1">Informar período desmarca as safras.</div>
                </div>
                <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
                    <div class="text-muted small"><strong>Análise por safra:</strong> {{ $contextoSafras }}</div>
                    <button class="btn btn-farmflow"><i class="bi bi-search me-1"></i> Atualizar DRE</button>
                </div>
            </div>
        </div>
    </form>

    <div class="ff-dre-kpis">
        <div class="ff-dre-kpi is-revenue"><span>Receita Total</span><strong class="text-success">{{ FarmFormat::money($totais['receitas']) }}</strong><small>100,00% da receita</small></div>
        <div class="ff-dre-kpi is-cost"><span>Custos Totais</span><strong class="text-warning">{{ FarmFormat::money($totais['custos']) }}</strong><small>{{ $percent($totais['custos']) }} da receita</small></div>
        <div class="ff-dre-kpi is-expense"><span>Despesas Totais</span><strong class="text-danger">{{ FarmFormat::money($totais['despesas']) }}</strong><small>{{ $percent($totais['despesas']) }} da receita</small></div>
        <div class="ff-dre-kpi is-result"><span>Resultado</span><strong class="{{ $resultClass }}">{{ FarmFormat::money($totais['resultado']) }}</strong><small>{{ $resultLabel }}</small></div>
        <div class="ff-dre-kpi is-margin"><span>Margem %</span><strong class="{{ $resultClass }}">{{ number_format($totais['margem'], 2, ',', '.') }}%</strong><small>Resultado sobre receita</small></div>
    </div>

    <div class="card ff-dre-exec-card mb-4">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <i class="bi bi-bar-chart-line me-2"></i>Receitas, custos, despesas e resultado
                <div class="text-muted small mt-1">Gráfico da análise por safras selecionadas.</div>
            </div>
            <span class="ff-dre-result-pill {{ ($totais['resultado'] ?? 0) >= 0 ? 'profit' : 'loss' }}">
                <i class="bi {{ ($totais['resultado'] ?? 0) >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' }}"></i>
                {{ ($totais['resultado'] ?? 0) >= 0 ? 'Lucro' : 'Prejuízo' }}: {{ FarmFormat::money($totais['resultado']) }}
            </span>
        </div>
        <div class="card-body">
            <canvas id="chartDrePrincipal"></canvas>
        </div>
    </div>

    <section class="panel">
        <div class="panel-head"><h2><i class="bi bi-table me-2"></i>Resumo gerencial</h2></div>
        <div class="table-wrap">
            <table class="ff-dre-summary-table">
                <thead>
                <tr>
                    <th>Grupo</th>
                    <th class="text-end">Valor Total</th>
                    <th class="text-end">% sobre Receita</th>
                    <th class="text-end">Ação</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>Receitas</strong></td>
                    <td class="text-end text-success fw-bold">{{ FarmFormat::money($totais['receitas']) }}</td>
                    <td class="text-end">100,00%</td>
                    <td class="text-end"><button type="button" class="ff-dre-toggle" data-dre-toggle="receitas"><i class="bi bi-chevron-right"></i> Expandir</button></td>
                </tr>
                @foreach ($receitas as $row)
                    <tr class="ff-dre-detail-row" data-dre-detail="receitas">
                        <td class="ff-dre-detail-label">{{ $row->categoria }}</td>
                        <td class="text-end text-success">{{ FarmFormat::money($row->valor_total) }}</td>
                        <td class="text-end">{{ $percent($row->valor_total) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Custos diretos</strong></td>
                    <td class="text-end text-warning fw-bold">{{ FarmFormat::money($totais['custos']) }}</td>
                    <td class="text-end">{{ $percent($totais['custos']) }}</td>
                    <td class="text-end"><button type="button" class="ff-dre-toggle" data-dre-toggle="custos"><i class="bi bi-chevron-right"></i> Expandir</button></td>
                </tr>
                @foreach ($custos as $row)
                    <tr class="ff-dre-detail-row" data-dre-detail="custos">
                        <td class="ff-dre-detail-label">{{ $row->categoria }}</td>
                        <td class="text-end text-warning">{{ FarmFormat::money($row->valor_total) }}</td>
                        <td class="text-end">{{ $percent($row->valor_total) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Despesas</strong></td>
                    <td class="text-end text-danger fw-bold">{{ FarmFormat::money($totais['despesas']) }}</td>
                    <td class="text-end">{{ $percent($totais['despesas']) }}</td>
                    <td class="text-end"><button type="button" class="ff-dre-toggle" data-dre-toggle="despesas"><i class="bi bi-chevron-right"></i> Expandir</button></td>
                </tr>
                @foreach ($despesas as $row)
                    <tr class="ff-dre-detail-row" data-dre-detail="despesas">
                        <td class="ff-dre-detail-label">
                            {{ $row->categoria }}
                            @if (!empty($row->grupo_dre))
                                <small class="d-block">{{ $row->grupo_dre }}</small>
                            @endif
                        </td>
                        <td class="text-end text-danger">{{ FarmFormat::money($row->valor_total) }}</td>
                        <td class="text-end">{{ $percent($row->valor_total) }}</td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="4">
                        <div class="ff-dre-mini-summary">
                            <div><span>Receita</span><strong class="text-success">{{ FarmFormat::money($totais['receitas']) }}</strong></div>
                            <div><span>Custos diretos</span><strong class="text-warning">{{ FarmFormat::money($totais['custos']) }}</strong></div>
                            <div><span>Despesas</span><strong class="text-danger">{{ FarmFormat::money($totais['despesas']) }}</strong></div>
                            <div><span>Resultado</span><strong class="{{ $resultClass }}">{{ FarmFormat::money($totais['resultado']) }}</strong></div>
                            <div><span>Margem</span><strong class="{{ $resultClass }}">{{ number_format($totais['margem'], 2, ',', '.') }}%</strong></div>
                        </div>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        const dreSafraChecks = Array.from(document.querySelectorAll('input[name="safras[]"]'));
        const dreSelectAllSafras = document.getElementById('dreSelecionarTodasSafras');
        function dreLimparPeriodo() {
            document.querySelectorAll('[data-dre-period]').forEach((input) => { input.value = ''; });
        }
        function dreAtualizarSelectAllSafras() {
            if (!dreSelectAllSafras) return;
            dreSelectAllSafras.checked = dreSafraChecks.length > 0 && dreSafraChecks.every((check) => check.checked);
        }
        dreSelectAllSafras?.addEventListener('change', () => {
            dreSafraChecks.forEach((check) => { check.checked = dreSelectAllSafras.checked; });
            if (dreSelectAllSafras.checked) dreLimparPeriodo();
        });
        dreSafraChecks.forEach((check) => {
            check.addEventListener('change', () => {
                if (check.checked) dreLimparPeriodo();
                dreAtualizarSelectAllSafras();
            });
        });
        document.querySelectorAll('[data-dre-period]').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.value) {
                    dreSafraChecks.forEach((check) => { check.checked = false; });
                    if (dreSelectAllSafras) dreSelectAllSafras.checked = false;
                }
            });
        });

        document.querySelectorAll('[data-dre-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const group = button.getAttribute('data-dre-toggle');
                const rows = document.querySelectorAll(`[data-dre-detail="${group}"]`);
                const open = Array.from(rows).some((row) => row.classList.contains('show'));
                rows.forEach((row) => row.classList.toggle('show', !open));
                button.innerHTML = `<i class="bi ${open ? 'bi-chevron-right' : 'bi-chevron-down'}"></i> ${open ? 'Expandir' : 'Recolher'}`;
            });
        });

        const dreCanvas = document.getElementById('chartDrePrincipal');
        if (dreCanvas && window.Chart) {
            new Chart(dreCanvas, {
                type: 'bar',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [{
                        label: 'Valor',
                        data: @json($chart['values']),
                        backgroundColor: ['#119c66', '#d89422', '#dc3545', '#0d6efd'],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { callback: v => window.ffMoneyBR ? window.ffMoneyBR(v) : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0)) } }
                    }
                }
            });
        }
    </script>
@endpush
