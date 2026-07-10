@extends('layouts.farmfort', ['title' => 'FarmFort - '.$title])

@php
    $talhaoLabels = $rows->pluck('nome')->values();
    $talhaoValues = $rows->map(fn ($row) => (float)$row->custo_ha)->values();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <a class="btn" href="{{ route('relatorios.index') }}">Relatórios</a>
    </div>

    <section class="panel">
        <form method="GET" action="{{ route('relatorios.talhao') }}" class="form-grid panel-body">
            <label>
                Safra
                <select name="safra_id">
                    @foreach ($safras as $item)
                        <option value="{{ $item->id }}" @selected($safraId == $item->id)>{{ $item->descricao }}</option>
                    @endforeach
                </select>
            </label>
            <div class="form-actions"><button class="btn primary" type="submit">Filtrar</button></div>
        </form>
    </section>

    @include('partials.stats', ['cards' => $cards])

    <section class="panel">
        <div class="panel-head"><h2>Custo por talhao (R$/ha)</h2></div>
        <div class="panel-body chart-box">
            @if ($rows->isNotEmpty())
                <canvas id="chartTalhao" height="260"></canvas>
            @else
                <p class="muted">Nenhum talhao ativo encontrado.</p>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Custos por talhão</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Talhão</th><th>Área</th><th>Lançamentos</th><th>Total</th><th>R$/ha</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><strong>{{ $row->nome }}</strong></td>
                            <td>{{ number_format($row->area ?? 0, 2, ',', '.') }} ha</td>
                            <td>{{ $row->qtd }}</td>
                            <td>R$ {{ number_format($row->total, 2, ',', '.') }}</td>
                            <td><strong>R$ {{ number_format($row->custo_ha, 2, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Nenhum talhão ativo encontrado.</td></tr>
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
            const moedaTalhaoBr = (value) => new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(Number(value || 0));

            new Chart(document.getElementById('chartTalhao'), {
                type: 'bar',
                data: {
                    labels: @json($talhaoLabels),
                    datasets: [{
                        label: 'R$/ha',
                        data: @json($talhaoValues),
                        backgroundColor: '#35c49a'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => moedaTalhaoBr(ctx.raw) } }
                    },
                    scales: {
                        x: { ticks: { callback: (value) => moedaTalhaoBr(value) } }
                    }
                }
            });
        </script>
    @endif
@endpush
