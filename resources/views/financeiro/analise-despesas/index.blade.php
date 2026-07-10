@extends('layouts.farmfort', [
    'title' => 'FarmFort - Análise de Categorias',
    'topbarLabel' => $topbarLabel ?? 'Análise de Categorias',
])

@php
    use App\Support\FarmFormat;

    $fmtMoney = fn ($value) => FarmFormat::money($value);
    $fmtArea = fn ($value) => $value > 0 ? number_format($value, 2, ',', '.').' ha' : '-';
    $fmtSc = fn ($value) => $value > 0 ? number_format($value, 2, ',', '.').' sc/ha' : 'Sem preço médio';
    $tipoAtual = $filtros['tipo'] ?? 'custos_despesas';
@endphp

@push('styles')
<style>
.ff-cat-page { display:flex; flex-direction:column; gap:18px; }
.ff-cat-toolbar { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.ff-cat-toolbar h1 { margin:4px 0 4px; font-size:28px; }
.ff-eyebrow { color:var(--ff-muted); font-size:11px; font-weight:900; letter-spacing:.02em; text-transform:uppercase; }
.ff-cat-filters { display:flex; align-items:flex-end; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-left:auto; }
.ff-cat-filters label { display:flex; flex-direction:column; gap:5px; min-width:128px; margin:0; }
.ff-cat-filters label span { color:var(--ff-muted); font-size:11px; font-weight:900; text-transform:uppercase; }
.ff-cat-filters select { min-height:42px; min-width:128px; }
.ff-cat-refresh { width:46px; min-height:42px; padding-inline:0; }
.ff-cat-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:14px; }
.ff-cat-kpi,
.ff-cat-card { background:var(--ff-surface); border:1px solid var(--ff-border); border-radius:8px; }
.ff-cat-kpi { position:relative; padding:18px; overflow:hidden; }
.ff-cat-kpi::before { content:""; position:absolute; inset:0 auto 0 0; width:4px; background:var(--cat-kpi-color, #64748b); }
.ff-cat-kpi-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
.ff-cat-kpi-head span { display:block; color:var(--ff-muted); font-size:11px; font-weight:900; text-transform:uppercase; }
.ff-cat-kpi strong { display:block; color:var(--ff-text); font-size:24px; line-height:1.1; }
.ff-cat-kpi small { display:block; margin-top:6px; color:var(--cat-kpi-color, var(--ff-muted)); font-size:11px; font-weight:800; }
.ff-cat-kpi-icon { width:34px; height:34px; display:grid; place-items:center; border:1px solid color-mix(in srgb, var(--cat-kpi-color, #64748b) 28%, transparent); border-radius:10px; color:var(--cat-kpi-color, #64748b); background:color-mix(in srgb, var(--cat-kpi-color, #64748b) 12%, transparent); }
.ff-cat-kpi-total { --cat-kpi-color:#0d8f68; }
.ff-cat-kpi-ha { --cat-kpi-color:#0d6efd; }
.ff-cat-kpi-area { --cat-kpi-color:#7952b3; }
.ff-cat-kpi-groups { --cat-kpi-color:#f59e0b; }
.ff-cat-kpi-count { --cat-kpi-color:#64748b; }
.ff-cat-grid { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(330px,.75fr); gap:14px; align-items:stretch; }
.ff-cat-card { padding:18px; min-height:520px; }
.ff-cat-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:12px; }
.ff-cat-card-head h2 { margin:0; color:var(--ff-text); font-size:18px; font-weight:900; }
.ff-cat-card-head p { margin:3px 0 0; color:var(--ff-muted); font-size:13px; }
.ff-cat-chart-badges { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
.ff-cat-price-badge { display:inline-flex; align-items:center; border:1px solid rgba(13,143,104,.22); border-radius:999px; padding:4px 9px; color:#0d8f68; background:rgba(13,143,104,.10); font-size:11px; font-weight:900; }
.ff-cat-chart-area { display:grid; grid-template-columns:180px minmax(300px,1fr); gap:16px; align-items:center; min-height:430px; }
.ff-cat-legend { align-self:stretch; display:flex; flex-direction:column; gap:6px; max-height:440px; overflow:auto; padding:8px 8px 8px 0; }
.ff-cat-legend-item { display:flex; align-items:center; gap:8px; width:100%; padding:8px; border:1px solid transparent; border-radius:8px; color:var(--ff-text); background:transparent; font-size:13px; font-weight:800; text-align:left; }
.ff-cat-legend-item:hover,
.ff-cat-legend-item.is-active { border-color:var(--ff-border); background:var(--ff-surface-2); }
.ff-cat-legend-dot,
.ff-cat-dot { width:10px; height:10px; border-radius:50%; flex:0 0 10px; }
.ff-cat-chart-wrap { position:relative; height:440px; }
.ff-cat-chart-wrap canvas { width:100% !important; height:100% !important; }
.ff-cat-empty { min-height:420px; display:grid; place-items:center; color:var(--ff-muted); font-weight:800; }
.ff-cat-sub-list { display:flex; flex-direction:column; gap:10px; }
.ff-cat-sub-item { border:1px solid var(--ff-border); border-radius:8px; padding:12px; background:var(--ff-surface-2); }
.ff-cat-sub-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; font-weight:900; }
.ff-cat-sub-meta { margin-top:3px; color:var(--ff-muted); font-size:12px; }
.ff-cat-bar { height:8px; margin-top:8px; border-radius:999px; overflow:hidden; background:var(--ff-surface-3); }
.ff-cat-bar span { display:block; height:100%; border-radius:999px; background:var(--ff-accent); }
.ff-cat-table { margin:0; }
.ff-cat-table th { color:var(--ff-muted); font-size:11px; text-transform:uppercase; }
.ff-cat-table td, .ff-cat-table th { vertical-align:middle; }
.ff-cat-group-cell { display:flex; align-items:center; gap:9px; }
@media (max-width: 1200px) {
  .ff-cat-grid { grid-template-columns:1fr; }
  .ff-cat-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 760px) {
  .ff-cat-kpis { grid-template-columns:1fr; }
  .ff-cat-chart-area { grid-template-columns:1fr; }
  .ff-cat-chart-wrap { height:360px; }
  .ff-cat-filters { width:100%; justify-content:stretch; }
  .ff-cat-filters label { min-width:100%; }
}
</style>
@endpush

@section('content')
<section class="ff-cat-page">
    <div class="ff-cat-toolbar">
        <div>
            <span class="ff-eyebrow">{{ $eyebrow }}</span>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <form class="ff-cat-filters" method="get" action="{{ route('financeiro.analise-despesas.index') }}">
            <label>
                <span>Safra</span>
                <select name="safra_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach ($safras as $safra)
                        <option value="{{ $safra->id }}" @selected($filtros['safra_id'] === $safra->id)>{{ $safra->descricao }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Categoria</span>
                <select name="categoria_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    @foreach ($categoriasFiltro as $categoria)
                        <option value="{{ $categoria->id }}" @selected($filtros['categoria_id'] === $categoria->id)>{{ $categoria->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Talhão</span>
                <select name="talhao_id" class="form-select" onchange="this.form.submit()" @disabled($tipoAtual === 'receitas')>
                    <option value="">Todos</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao->id }}" @selected($filtros['talhao_id'] === $talhao->id)>{{ $talhao->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Tipo</span>
                <select name="tipo" class="form-select" onchange="this.form.submit()">
                    <option value="custos_despesas" @selected($tipoAtual === 'custos_despesas')>Todos</option>
                    <option value="despesas" @selected($tipoAtual === 'despesas')>Despesas</option>
                    <option value="receitas" @selected($tipoAtual === 'receitas')>Receitas</option>
                </select>
            </label>
            <button class="btn primary ff-cat-refresh" type="submit" title="Aplicar filtros" aria-label="Aplicar filtros">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </form>
    </div>

    <div class="ff-cat-kpis">
        <article class="ff-cat-kpi ff-cat-kpi-total">
            <div class="ff-cat-kpi-head"><span>Total analisado</span><div class="ff-cat-kpi-icon"><i class="bi bi-cash-stack"></i></div></div>
            <strong>{{ $fmtMoney($total) }}</strong>
        </article>
        <article class="ff-cat-kpi ff-cat-kpi-ha">
            <div class="ff-cat-kpi-head"><span>Custo/ha</span><div class="ff-cat-kpi-icon"><i class="bi bi-slash-square"></i></div></div>
            <strong>{{ $area > 0 ? $fmtMoney($valorHa).'/ha' : '-' }}</strong>
            <small>{{ $fmtSc($sacasHa) }}</small>
        </article>
        <article class="ff-cat-kpi ff-cat-kpi-area">
            <div class="ff-cat-kpi-head"><span>Área considerada</span><div class="ff-cat-kpi-icon"><i class="bi bi-map"></i></div></div>
            <strong>{{ $fmtArea($area) }}</strong>
        </article>
        <article class="ff-cat-kpi ff-cat-kpi-groups">
            <div class="ff-cat-kpi-head"><span>Grupos</span><div class="ff-cat-kpi-icon"><i class="bi bi-diagram-3"></i></div></div>
            <strong>{{ $categoriasResumo->count() }}</strong>
        </article>
        <article class="ff-cat-kpi ff-cat-kpi-count">
            <div class="ff-cat-kpi-head"><span>Lançamentos</span><div class="ff-cat-kpi-icon"><i class="bi bi-list-check"></i></div></div>
            <strong>{{ $totalLancamentos }}</strong>
        </article>
    </div>

    <div class="ff-cat-grid">
        <section class="ff-cat-card">
            <div class="ff-cat-card-head">
                <div>
                    <h2>Distribuição por grupo gerencial</h2>
                    <p>Passe o cursor para destacar; clique para travar o detalhamento.</p>
                </div>
                <div class="ff-cat-chart-badges">
                    <span class="badge bg-primary">{{ $tipoAtual === 'receitas' ? 'Receitas' : ($tipoAtual === 'despesas' ? 'Despesas' : 'Todos') }}</span>
                    <span class="ff-cat-price-badge">{{ $precoMedio > 0 ? 'Soja '.$fmtMoney($precoMedio).'/sc' : 'Soja sem preço médio' }}</span>
                </div>
            </div>

            @if ($categoriasResumo->isNotEmpty())
                <div class="ff-cat-chart-area">
                    <div class="ff-cat-legend">
                        @foreach ($categoriasResumo as $idx => $categoria)
                            <button type="button" class="ff-cat-legend-item" data-cat-index="{{ $idx }}">
                                <span class="ff-cat-legend-dot" style="background:{{ $categoria['color'] }}"></span>
                                <span>{{ $categoria['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="ff-cat-chart-wrap">
                        <canvas id="chartCategoriasFinanceiro"></canvas>
                    </div>
                </div>
            @else
                <div class="ff-cat-empty">Sem lançamentos para os filtros selecionados.</div>
            @endif
        </section>

        <aside class="ff-cat-card">
            <div class="ff-cat-card-head">
                <div>
                    <h2 id="ffCatSelectedTitle">Detalhamento</h2>
                    <p id="ffCatSelectedMeta">Clique em uma categoria do gráfico para analisar.</p>
                </div>
            </div>
            <div id="ffCatSubList" class="ff-cat-sub-list">
                <div class="text-muted">Nenhuma categoria selecionada.</div>
            </div>
        </aside>
    </div>

    <section class="ff-cat-card" style="min-height:0;">
        <div class="ff-cat-card-head">
            <div>
                <h2>Resumo por grupo gerencial</h2>
                <p>{{ $categoriasResumo->count() }} grupo(s) | {{ $totalLancamentos }} lançamento(s)</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm ff-cat-table">
                <thead>
                    <tr><th>Grupo</th><th>Lançamentos</th><th>Total</th><th>R$/ha</th><th>sc/ha</th><th>%</th></tr>
                </thead>
                <tbody>
                    @forelse ($categoriasResumo as $categoria)
                        @php
                            $valor = (float)$categoria['value'];
                            $valorHaCategoria = $area > 0 ? $valor / $area : 0;
                            $scHaCategoria = ($valorHaCategoria > 0 && $precoMedio > 0) ? $valorHaCategoria / $precoMedio : 0;
                            $pct = $total > 0 ? ($valor / $total) * 100 : 0;
                        @endphp
                        <tr>
                            <td><span class="ff-cat-group-cell"><span class="ff-cat-dot" style="background:{{ $categoria['color'] }}"></span><strong>{{ $categoria['label'] }}</strong></span></td>
                            <td>{{ $categoria['count'] }}</td>
                            <td>{{ $fmtMoney($valor) }}</td>
                            <td>{{ $valorHaCategoria > 0 ? $fmtMoney($valorHaCategoria).'/ha' : '-' }}</td>
                            <td>{{ $scHaCategoria > 0 ? number_format($scHaCategoria, 2, ',', '.') : '-' }}</td>
                            <td>{{ number_format($pct, 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">Nenhum dado encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection

@push('scripts')
@if ($categoriasResumo->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var catData = @json($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    var canvas = document.getElementById('chartCategoriasFinanceiro');
    if (!canvas || !window.Chart) return;

    var currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    var titleEl = document.getElementById('ffCatSelectedTitle');
    var metaEl = document.getElementById('ffCatSelectedMeta');
    var listEl = document.getElementById('ffCatSubList');
    var legendItems = Array.from(document.querySelectorAll('[data-cat-index]'));
    var activeIndex = null;

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[char];
        });
    }

    function renderDetail(index, locked) {
        var cat = catData.categories[index];
        if (!cat) return;
        var total = Number(cat.value || 0);
        titleEl.textContent = cat.label;
        metaEl.textContent = (locked ? 'Fixado - ' : '') + currency.format(total) + ' em ' + Number(cat.count || 0).toLocaleString('pt-BR') + ' lançamento(s)';
        listEl.innerHTML = (cat.subcategories || []).map(function (sub) {
            var value = Number(sub.value || 0);
            var pct = total > 0 ? (value / total) * 100 : 0;
            return '<div class="ff-cat-sub-item">' +
                '<div class="ff-cat-sub-top"><span>' + escapeHtml(sub.label) + '</span><strong>' + currency.format(value) + '</strong></div>' +
                '<div class="ff-cat-sub-meta">' + Number(sub.count || 0).toLocaleString('pt-BR') + ' lançamento(s) - ' + pct.toFixed(1).replace('.', ',') + '% da categoria</div>' +
                '<div class="ff-cat-bar"><span style="width:' + Math.max(2, pct).toFixed(2) + '%;background:' + cat.color + '"></span></div>' +
            '</div>';
        }).join('');
        legendItems.forEach(function (item) {
            item.classList.toggle('is-active', Number(item.dataset.catIndex) === index);
        });
    }

    var centerText = {
        id: 'farmfortCenterText',
        afterDraw: function (chart) {
            var meta = chart.getDatasetMeta(0);
            if (!meta || !meta.data || !meta.data.length) return;
            var x = meta.data[0].x;
            var y = meta.data[0].y;
            var selected = activeIndex === null ? null : catData.categories[activeIndex];
            var label = selected ? selected.label : 'Total';
            var value = selected ? selected.value : catData.total;
            var ctx = chart.ctx;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--ff-muted') || '#94a3b8';
            ctx.font = '700 13px system-ui, sans-serif';
            ctx.fillText(label, x, y - 18);
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--ff-text') || '#e5eef7';
            ctx.font = '900 23px system-ui, sans-serif';
            ctx.fillText(currency.format(Number(value || 0)), x, y + 7);
            ctx.restore();
        }
    };

    var chart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: catData.categories.map(function (cat) { return cat.label; }),
            datasets: [{
                data: catData.categories.map(function (cat) { return Number(cat.value || 0); }),
                backgroundColor: catData.categories.map(function (cat) { return cat.color; }),
                borderColor: getComputedStyle(document.documentElement).getPropertyValue('--ff-surface') || '#17212b',
                borderWidth: 3,
                hoverOffset: 14
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '64%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            return ctx.label + ': ' + currency.format(Number(ctx.raw || 0));
                        }
                    }
                }
            },
            onHover: function (event, elements) {
                if (activeIndex !== null || !elements.length) return;
                renderDetail(elements[0].index, false);
            },
            onClick: function (event, elements) {
                if (!elements.length) return;
                activeIndex = elements[0].index;
                renderDetail(activeIndex, true);
                chart.update();
            }
        },
        plugins: [centerText]
    });

    legendItems.forEach(function (item) {
        item.addEventListener('mouseenter', function () {
            if (activeIndex !== null) return;
            renderDetail(Number(item.dataset.catIndex), false);
        });
        item.addEventListener('click', function () {
            activeIndex = Number(item.dataset.catIndex);
            renderDetail(activeIndex, true);
            chart.update();
        });
    });

    canvas.addEventListener('mouseleave', function () {
        if (activeIndex !== null) return;
        titleEl.textContent = 'Detalhamento';
        metaEl.textContent = 'Clique em uma categoria do gráfico para analisar.';
        listEl.innerHTML = '<div class="text-muted">Nenhuma categoria selecionada.</div>';
        legendItems.forEach(function (item) { item.classList.remove('is-active'); });
    });
});
</script>
@endif
@endpush
