@extends('layouts.farmfort', ['title' => 'FarmFort - Orçado x Realizado'])

@php
    use App\Support\FarmFormat;
@endphp

@section('content')
    <style>
        .ff-or-head { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:16px; }
        .ff-or-head h1 { margin:0 0 6px; font-size:24px; font-weight:900; }
        .ff-or-head p { margin:0; color:var(--ff-muted); }
        .ff-or-filters { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:12px; margin-bottom:16px; padding:14px; border:1px solid var(--ff-border); border-radius:8px; background:var(--ff-surface); }
        .ff-or-filters label, .ff-or-filters .ff-or-type { display:grid; gap:6px; min-width:0; }
        .ff-or-filters span { color:var(--ff-muted); font-size:11px; font-weight:900; text-transform:uppercase; }
        .ff-or-type-filter { display:flex; gap:8px; flex-wrap:wrap; align-items:center; min-height:38px; }
        .ff-or-type-filter label { display:flex; grid-template-columns:auto 1fr; align-items:center; gap:6px; font-weight:700; }
        .ff-or-analysis-layout { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(360px,.8fr); gap:14px; }
        .ff-or-chart-card { min-height:0; height:610px; padding:22px; overflow:hidden; border:1px solid var(--ff-border); border-radius:8px; background:var(--ff-surface); box-shadow:0 1px 2px rgba(15,23,42,.05); }
        .ff-or-chart-card canvas { width:100% !important; height:510px !important; min-height:0; }
        .ff-or-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; }
        .ff-or-card-head h2 { margin:0; font-size:11px; text-transform:uppercase; color:var(--ff-muted); font-weight:900; }
        .ff-or-card-head span { display:block; margin-top:6px; color:var(--ff-text); font-size:17px; font-weight:950; }
        .ff-or-side { display:flex; flex-direction:column; gap:10px; min-width:0; height:610px; padding:10px; border:1px solid var(--ff-border); border-radius:8px; background:var(--ff-surface); box-shadow:0 1px 2px rgba(15,23,42,.05); overflow:hidden; }
        .ff-or-side-kpis { flex:0 0 auto; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); overflow:hidden; border:1px solid var(--ff-border); border-radius:8px; background:var(--ff-surface-2); }
        .ff-or-side-kpis div { min-width:0; padding:9px 6px; text-align:center; border-right:1px solid var(--ff-border); }
        .ff-or-side-kpis div:last-child { border-right:0; }
        .ff-or-side-kpis strong { display:block; font-size:14px; line-height:1.15; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .ff-or-side-kpis span { display:block; color:var(--ff-muted); font-size:10px; margin-top:5px; font-weight:900; text-transform:uppercase; }
        .ff-or-plan-table { flex:1 1 auto; min-height:0; overflow:auto; border:1px solid var(--ff-border); border-radius:8px; background:var(--ff-surface); }
        .ff-or-plan-table table { width:100%; margin:0; font-size:11px; table-layout:fixed; }
        .ff-or-plan-table th { position:sticky; top:0; z-index:1; background:#28491c; color:#fff; font-weight:900; white-space:nowrap; }
        .ff-or-plan-table th, .ff-or-plan-table td { padding:7px 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:1.22; border-bottom:1px solid var(--ff-border); }
        .ff-or-plan-table tbody tr:nth-child(even) td { background:rgba(148,163,184,.07); }
        .ff-or-plan-table tfoot td { position:sticky; bottom:0; background:#927236; color:#fff; font-weight:900; }
        @media (max-width: 1200px) { .ff-or-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } .ff-or-analysis-layout { grid-template-columns:1fr; } .ff-or-side, .ff-or-chart-card { height:auto; } }
        @media (max-width: 640px) { .ff-or-filters { grid-template-columns:1fr; } }
    </style>

    <header class="ff-or-head">
        <div>
            <h1>Orçado x Realizado</h1>
            <p>Compare o planejamento da safra com a execução financeira realizada no período filtrado.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Exportar</button>
    </header>

    <form class="ff-or-filters" method="get">
        <label>
            <span>Safra</span>
            <select name="safra_id" class="form-select">
                <option value="">Todos</option>
                @foreach ($safras as $safra)
                    <option value="{{ $safra->id }}" @selected((int)($filtros['safra_id'] ?? 0) === (int)$safra->id)>{{ $safra->descricao }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Categoria</span>
            <select name="categoria_id" class="form-select">
                <option value="">Todas</option>
                @foreach ($categoriasFiltro as $categoria)
                    <option value="{{ $categoria->id }}" @selected((int)($filtros['categoria_id'] ?? 0) === (int)$categoria->id)>{{ $categoria->nome }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Período inicial</span>
            <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}">
        </label>
        <label>
            <span>Período final</span>
            <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}">
        </label>
        <div class="ff-or-type">
            <span>Tipo</span>
            <div class="ff-or-type-filter">
                <label><input type="radio" name="tipo" value="receita" @checked(($filtros['tipo'] ?? 'todos') === 'receita')> Receita</label>
                <label><input type="radio" name="tipo" value="custos_despesas" @checked(($filtros['tipo'] ?? 'todos') === 'custos_despesas')> Custo/Despesa</label>
            </div>
        </div>
        <div class="d-flex align-items-end gap-2">
            <button class="btn btn-farmflow" type="submit">Filtrar</button>
            <a class="btn btn-outline-secondary" href="{{ route('relatorios.orcado-realizado') }}">Limpar</a>
        </div>
    </form>

    <div class="ff-or-analysis-layout">
        <section class="ff-or-chart-card">
            <div class="ff-or-card-head">
                <div>
                    <h2>Valor orçado x realizado</h2>
                    <span>Ao longo do tempo</span>
                </div>
            </div>
            <canvas id="chartOrcadoRealizado"></canvas>
        </section>

        <aside class="ff-or-side">
            <div class="ff-or-side-kpis">
                <div><strong class="text-warning">{{ FarmFormat::money($totais['orcado']) }}</strong><span>Orçado</span></div>
                <div><strong class="text-primary">{{ FarmFormat::money($totais['realizado']) }}</strong><span>Realizado</span></div>
                <div><strong class="{{ $totais['diferenca'] >= 0 ? 'text-success' : 'text-danger' }}">{{ FarmFormat::money($totais['diferenca']) }}</strong><span>Diferença</span></div>
                <div><strong>{{ number_format($totais['execucao'], 2, ',', '.') }}%</strong><span>% Atingido</span></div>
            </div>
            <div class="ff-or-plan-table">
                <table>
                    <thead>
                    <tr>
                        <th>Sub-Categoria</th>
                        <th class="text-end">Orçado</th>
                        <th class="text-end">Realizado</th>
                        <th class="text-end">Diferença</th>
                        <th class="text-end">% Atingido</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->categoria }}</td>
                            <td class="text-end">{{ $row->orcado }}</td>
                            <td class="text-end">{{ $row->realizado }}</td>
                            <td class="text-end">{{ $row->diferenca }}</td>
                            <td class="text-end">{{ $row->execucao }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Sem dados para o filtro selecionado.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="text-end">{{ FarmFormat::money($totais['orcado']) }}</td>
                        <td class="text-end">{{ FarmFormat::money($totais['realizado']) }}</td>
                        <td class="text-end">{{ FarmFormat::money($totais['diferenca']) }}</td>
                        <td class="text-end">{{ number_format($totais['execucao'], 2, ',', '.') }}%</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        const orCanvas = document.getElementById('chartOrcadoRealizado');
        if (orCanvas && window.Chart) {
            new Chart(orCanvas, {
                type: 'bar',
                data: {
                    labels: @json($chart['labels']),
                    datasets: [
                        { label: 'Orçado', data: @json($chart['orcado']), backgroundColor: 'rgba(216,148,34,.72)', borderRadius: 6 },
                        { label: 'Realizado', data: @json($chart['realizado']), backgroundColor: 'rgba(13,110,253,.72)', borderRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { ticks: { autoSkip: false, maxRotation: 55, minRotation: 0 } },
                        y: { ticks: { callback: v => window.ffMoneyBR ? window.ffMoneyBR(v) : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0)) } }
                    }
                }
            });
        }
    </script>
@endpush
