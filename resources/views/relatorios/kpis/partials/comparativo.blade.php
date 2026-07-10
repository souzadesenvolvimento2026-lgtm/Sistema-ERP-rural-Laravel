@php
    $comparativoLabels = $comparativo->pluck('descricao')->values();
    $comparativoDespesas = $comparativo->pluck('total_despesas')->map(fn ($value) => (float)$value)->values();
    $comparativoReceitas = $comparativo->pluck('total_receitas')->map(fn ($value) => (float)$value)->values();
@endphp

<section class="panel">
    <div class="panel-head"><h2>Comparativo entre safras</h2></div>
    <div class="panel-body chart-box">
        @if ($comparativo->isNotEmpty())
            <canvas id="chartComp" height="220"></canvas>
        @else
            <p class="muted">Nenhuma safra encontrada.</p>
        @endif
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Safra</th>
                    <th>Despesa</th>
                    <th>Receita</th>
                    <th>Resultado</th>
                    <th>Area</th>
                    <th>Producao realizada</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comparativo as $item)
                    <tr>
                        <td>{{ $item->descricao }}</td>
                        <td>{{ \App\Support\FarmFormat::money($item->total_despesas) }}</td>
                        <td>{{ \App\Support\FarmFormat::money($item->total_receitas) }}</td>
                        <td><strong class="{{ $item->resultado >= 0 ? 'success' : 'danger' }}">{{ \App\Support\FarmFormat::money($item->resultado) }}</strong></td>
                        <td>{{ number_format((float)$item->area_plantada, 2, ',', '.') }} ha</td>
                        <td>{{ number_format((float)$item->producao_realizada, 2, ',', '.') }} sc</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Nenhuma safra encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@push('scripts')
    @if ($comparativo->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
            const moedaComparativoBr = (value) => new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(Number(value || 0));

            new Chart(document.getElementById('chartComp'), {
                type: 'bar',
                data: {
                    labels: @json($comparativoLabels),
                    datasets: [
                        {
                            label: 'Despesas',
                            data: @json($comparativoDespesas),
                            backgroundColor: 'rgba(239, 68, 68, 0.75)'
                        },
                        {
                            label: 'Receitas',
                            data: @json($comparativoReceitas),
                            backgroundColor: 'rgba(53, 196, 154, 0.75)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${moedaComparativoBr(ctx.raw)}` } }
                    },
                    scales: {
                        y: { ticks: { callback: (value) => moedaComparativoBr(value) } }
                    }
                }
            });
        </script>
    @endif
@endpush
