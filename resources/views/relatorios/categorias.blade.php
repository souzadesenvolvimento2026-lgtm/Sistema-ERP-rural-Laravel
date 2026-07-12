@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@section('content')
    <div class="page-head">
        <div>
            <h1>Relatório por Categoria</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn" href="{{ route('relatorios.index') }}">Relatórios</a>
    </div>

    <section class="panel">
        <form method="GET" action="{{ route('relatorios.categorias') }}" class="form-grid panel-body">
            <label>
                Tipo
                <select name="tipo">
                    <option value="custos_despesas" @selected($filtros['tipo'] === 'custos_despesas')>Custos e despesas</option>
                    <option value="despesas" @selected($filtros['tipo'] === 'despesas')>Despesas</option>
                    <option value="receitas" @selected($filtros['tipo'] === 'receitas')>Receitas</option>
                </select>
            </label>
            <label>
                Safra
                <select name="safra_id">
                    <option value="">Todas</option>
                    @foreach ($safras as $safra)
                        <option value="{{ $safra->id }}" @selected($safraId == $safra->id)>{{ $safra->descricao }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Categoria
                <select name="categoria_id">
                    <option value="">Todas</option>
                    @foreach ($categoriasFiltro as $categoria)
                        <option value="{{ $categoria->id }}" @selected($filtros['categoria_id'] == $categoria->id)>{{ $categoria->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Talhão
                <select name="talhao_id">
                    <option value="">Todos</option>
                    @foreach ($talhoes as $talhao)
                        <option value="{{ $talhao->id }}" @selected($filtros['talhao_id'] == $talhao->id)>{{ $talhao->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Início
                <input type="date" name="data_inicio" value="{{ $filtros['data_inicio'] }}">
            </label>
            <label>
                Fim
                <input type="date" name="data_fim" value="{{ $filtros['data_fim'] }}">
            </label>
            <div class="form-actions">
                <button class="btn primary" type="submit">Filtrar</button>
                <button class="btn" type="button" onclick="window.print()">Imprimir</button>
            </div>
        </form>
    </section>

    @include('partials.stats', ['cards' => $cards])

    <section class="chart-grid">
        <div class="panel">
            <div class="panel-head"><h2>Distribuição por categoria</h2></div>
            <div class="panel-body chart-box">
                @if ($rows->isNotEmpty())
                    <canvas id="chartCategorias" height="240"></canvas>
                @else
                    <p class="muted">Sem dados para esta safra.</p>
                @endif
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h2>Total por tipo</h2></div>
            <div class="panel-body chart-box">
                @if ($rows->isNotEmpty())
                    <canvas id="chartTipos" height="240"></canvas>
                @else
                    <p class="muted">Sem dados para esta safra.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Detalhamento</h2></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Tipo</th>
                        <th>Lançamentos</th>
                        <th>Total</th>
                        <th>Pago</th>
                        <th>Pendente</th>
                        <th>% do total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><span class="color-chip" style="background: {{ $row->cor }}"></span>{{ $row->nome }}</td>
                            <td>{{ ucfirst($row->tipo) }}</td>
                            <td>{{ $row->qtd }}</td>
                            <td><strong>R$ {{ number_format($row->total, 2, ',', '.') }}</strong></td>
                            <td>R$ {{ number_format($row->pago, 2, ',', '.') }}</td>
                            <td>R$ {{ number_format($row->pendente, 2, ',', '.') }}</td>
                            <td>
                                {{ number_format($row->percentual, 1, ',', '.') }}%
                                <div class="progress-line">
                                    <span style="width: {{ $row->progresso_percentual }}%; background: {{ $row->cor ?: '#35c49a' }}"></span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">Sem despesas aprovadas para a safra selecionada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    @if ($rows->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
            const moedaBr = (value) => new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(Number(value || 0));

            new Chart(document.getElementById('chartCategorias'), {
                type: 'doughnut',
                data: {
                    labels: @json($chart['categoria_labels']),
                    datasets: [{
                        data: @json($chart['categoria_values']),
                        backgroundColor: @json($chart['categoria_colors']),
                        borderWidth: 2,
                        borderColor: '#182431'
                    }]
                },
                options: {
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${moedaBr(ctx.raw)}` } }
                    },
                    cutout: '55%'
                }
            });

            new Chart(document.getElementById('chartTipos'), {
                type: 'bar',
                data: {
                    labels: @json($chart['tipo_labels']),
                    datasets: [{
                        label: 'Total',
                        data: @json($chart['tipo_values']),
                        backgroundColor: '#35c49a'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => moedaBr(ctx.raw) } }
                    },
                    scales: {
                        x: { ticks: { callback: (value) => moedaBr(value) } }
                    }
                }
            });
        </script>
    @endif
@endpush
