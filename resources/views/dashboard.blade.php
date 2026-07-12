@extends('layouts.farmfort', ['title' => 'FarmFort - Central da Safra'])

@php
    use App\Support\FarmFormat;

    $fmtMoney = fn ($value) => FarmFormat::money($value);
    $fmtNum = fn ($value, $places = 1) => number_format((float) $value, $places, ',', '.');
@endphp

@section('content')
    <section class="ff-bi-hero">
        <div>
            <span class="ff-eyebrow">Dashboard financeiro</span>
            <h1>{{ $property->nome ?? 'Propriedade' }}</h1>
            <p>Visão executiva consolidada com os dados lançados nos módulos do FarmFort.</p>
        </div>
        <div class="ff-bi-hero-facts">
            <div><span>Safra</span><strong>{{ $safra->descricao ?? '-' }}</strong></div>
            <div><span>Área plantada</span><strong>{{ $fmtNum($areaPlantada, 2) }} ha</strong></div>
            <div><span>Cotação soja</span><strong>{{ $fmtMoney($cotacaoSoja) }}/sc</strong><small>{{ $cotacaoUltima }}</small></div>
        </div>
    </section>

    <section class="ff-bi-kpis">
        <div class="ff-bi-kpi is-result">
            <span>Resultado da safra</span>
            <strong>{{ $fmtMoney($saldoSafra) }}</strong>
            <small>Projetado {{ $fmtMoney($resultadoProjetado) }} · desvio {{ $fmtMoney($desvioResultado) }}</small>
        </div>
        <div class="ff-bi-kpi">
            <span>Receita realizada</span>
            <strong>{{ $fmtMoney($totalReceitas) }}</strong>
            <small>{{ $fmtNum($execucaoReceita) }}% do orçamento</small>
        </div>
        <div class="ff-bi-kpi">
            <span>Despesa realizada</span>
            <strong>{{ $fmtMoney($totalDespesas) }}</strong>
            <small>{{ $fmtNum($execucaoDespesa) }}% do orçamento</small>
        </div>
        <div class="ff-bi-kpi">
            <span>Saldo em contas</span>
            <strong>{{ $fmtMoney($saldoContas) }}</strong>
            <small>{{ $fmtMoney($receitasAReceber) }} a receber · {{ $fmtMoney($despesasAPagar) }} a pagar</small>
        </div>
        <div class="ff-bi-kpi">
            <span>Margem</span>
            <strong>{{ $fmtNum($margem) }}%</strong>
            <small>Custo médio {{ $fmtMoney($custoHa) }}/ha</small>
        </div>
        <div class="ff-bi-kpi">
            <span>Colheita</span>
            <strong>{{ $fmtNum($sacasColhidas, 0) }} sc</strong>
            <small>{{ $fmtNum($produtividade) }} sc/ha · {{ $fmtNum($colheitaPct) }}%</small>
        </div>
    </section>

    <section class="ff-bi-grid ff-bi-grid-main">
        <div class="ff-bi-panel ff-bi-panel-wide">
            <header><span>Fluxo de Caixa</span><strong>Entradas, saídas e saldo acumulado em {{ $year }}</strong></header>
            <div class="ff-bi-chart"><canvas id="chartFluxoCaixa"></canvas></div>
            <div class="ff-bi-summary">
                <div><span>Recebido</span><strong>{{ $fmtMoney($receitasRecebidas) }}</strong></div>
                <div><span>Pago</span><strong>{{ $fmtMoney($despesasPagas) }}</strong></div>
                <div><span>Saldo financeiro</span><strong>{{ $fmtMoney($saldoFinanceiro) }}</strong></div>
            </div>
        </div>

        <div class="ff-bi-panel">
            <header><span>DRE</span><strong>Resultado gerencial da safra</strong></header>
            <div class="ff-bi-dre">
                @foreach ($dreLinhas as $linha)
                    <div class="{{ $linha['classe'] }}">
                        <span>{{ $linha['label'] }}</span>
                        <strong>{{ $fmtMoney($linha['valor']) }}</strong>
                    </div>
                @endforeach
            </div>
            <div class="ff-bi-footnote">Margem calculada sobre receitas lançadas na safra selecionada.</div>
        </div>
    </section>

    <section class="ff-bi-grid">
        <div class="ff-bi-panel ff-bi-panel-wide">
            <header><span>Controle Categoria</span><strong>Orçado x realizado por categoria de despesa</strong></header>
            <div class="ff-bi-category-list">
                @forelse ($budgetRows as $row)
                    <div class="ff-bi-category-row">
                        <div><i style="--cat-color:{{ $row->cor ?: '#35bd91' }}"></i><strong>{{ $row->nome }}</strong></div>
                        <span>{{ $fmtMoney($row->valor_previsto) }}</span>
                        <span>{{ $fmtMoney($row->realizado) }}</span>
                        <span class="{{ $row->desvio_classe }}">{{ $fmtMoney($row->desvio) }}</span>
                        <em>{{ $fmtNum($row->percentual_execucao) }}%</em>
                        <b><i style="width:{{ $row->progresso_execucao }}%"></i></b>
                    </div>
                @empty
                    <div class="ff-bi-empty">Sem orçamento de despesas lançado para esta safra.</div>
                @endforelse
            </div>
        </div>

        <div class="ff-bi-panel">
            <header><span>Indicadores</span><strong>Leitura rápida da operação</strong></header>
            <div class="ff-bi-indicators">
                <div><span>Execução do resultado</span><strong>{{ $fmtNum($execucaoResultado) }}%</strong></div>
                <div><span>Custo em soja</span><strong>{{ $fmtNum($custoSacasSojaTotal, 2) }} sc</strong></div>
                <div><span>Contratado</span><strong>{{ $fmtNum($contratado, 0) }} sc</strong></div>
                <div><span>Entregue</span><strong>{{ $fmtNum($entregue, 0) }} sc</strong></div>
                <div><span>Máquinas ativas</span><strong>{{ (int) $maquinasAtivas }}</strong></div>
                <div><span>NF no mês</span><strong>{{ (int) $nfsMes }}</strong></div>
            </div>
        </div>
    </section>

    <section class="ff-bi-grid ff-bi-grid-three">
        <div class="ff-bi-panel">
            <header><span>Orç. Receitas</span><strong>Planejado x lançado</strong></header>
            <div class="ff-bi-budget-total">
                <div><span>Projetado</span><strong>{{ $fmtMoney($receitaProjetada) }}</strong></div>
                <div><span>Lançado</span><strong>{{ $fmtMoney($totalReceitas) }}</strong></div>
                <div><span>A receber</span><strong>{{ $fmtMoney($receitasAReceber) }}</strong></div>
            </div>
            <div class="ff-bi-mini-table">
                @forelse ($receitasPorComprador as $receita)
                    <div><span>{{ $receita->nome }}</span><strong>{{ $fmtMoney($receita->total) }}</strong></div>
                @empty
                    <div><span>Sem receitas por comprador</span><strong>{{ $fmtMoney(0) }}</strong></div>
                @endforelse
            </div>
        </div>

        <div class="ff-bi-panel">
            <header><span>Orç. Despesas</span><strong>Planejado x executado</strong></header>
            <div class="ff-bi-budget-total">
                <div><span>Projetado</span><strong>{{ $fmtMoney($despesaProjetada) }}</strong></div>
                <div><span>Realizado</span><strong>{{ $fmtMoney($totalDespesas) }}</strong></div>
                <div><span>A pagar</span><strong>{{ $fmtMoney($despesasAPagar) }}</strong></div>
            </div>
            <div class="ff-bi-progress-xl"><i style="width:{{ min(100, $execucaoDespesa) }}%"></i></div>
            <div class="ff-bi-footnote">{{ $fmtNum($execucaoDespesa) }}% do orçamento de despesas utilizado.</div>
        </div>

        <div class="ff-bi-panel">
            <header><span>Saldo em Contas</span><strong>Posição financeira por conta</strong></header>
            <div class="ff-bi-mini-table">
                @forelse ($contasSaldoRows as $conta)
                    <div>
                        <span>{{ $conta->nome }}<small>{{ $conta->banco ?: ucfirst((string) $conta->tipo) }}</small></span>
                        <strong>{{ $fmtMoney($conta->saldo_atual) }}</strong>
                    </div>
                @empty
                    <div><span>Nenhuma conta cadastrada</span><strong>{{ $fmtMoney(0) }}</strong></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="ff-bi-grid">
        <div class="ff-bi-panel">
            <header><span>Alertas</span><strong>Pontos de atenção</strong></header>
            <div class="ff-bi-mini-table">
                @foreach ($alertas as $alerta)
                    <div><span><i class="bi {{ $alerta['icon'] }}"></i> {{ $alerta['title'] }}<small>{{ $alerta['text'] }}</small></span></div>
                @endforeach
            </div>
        </div>

        <div class="ff-bi-panel ff-bi-panel-wide">
            <header><span>Últimos lançamentos</span><strong>Despesas e pedidos recentes</strong></header>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Pessoa</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($recentExpenses as $row)
                        <tr>
                            <td>{{ FarmFormat::date($row->data_lancamento) }}</td>
                            <td>{{ $row->descricao }}</td>
                            <td>{{ $row->fornecedor ?: '-' }}</td>
                            <td>{{ $fmtMoney($row->valor_total) }}</td>
                            <td><span class="pill warning">{{ FarmFormat::statusLabel($row->status_pagamento) }}</span></td>
                        </tr>
                    @endforeach
                    @foreach ($recentOrders as $row)
                        <tr>
                            <td>{{ FarmFormat::date($row->issue_date) }}</td>
                            <td>Pedido fiscal {{ $row->order_number }}</td>
                            <td>{{ $row->supplier_name ?: '-' }}</td>
                            <td>{{ $fmtMoney($row->total_value) }}</td>
                            <td><span class="pill success">{{ FarmFormat::statusLabel($row->status) }}</span></td>
                        </tr>
                    @endforeach
                    @if ($recentExpenses->isEmpty() && $recentOrders->isEmpty())
                        <tr><td colspan="5">Nenhum lançamento encontrado.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var canvas = document.getElementById('chartFluxoCaixa');
            if (!canvas || !window.Chart) {
                return;
            }

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: @json($fluxo['labels']),
                    datasets: [
                        { label: 'Entradas', data: @json($fluxo['entradas']), borderColor: '#35bd91', backgroundColor: 'rgba(53,189,145,.15)', tension: .35 },
                        { label: 'Saídas', data: @json($fluxo['saidas']), borderColor: '#ff6473', backgroundColor: 'rgba(255,100,115,.12)', tension: .35 },
                        { label: 'Saldo', data: @json($fluxo['saldo']), borderColor: '#66a6ff', backgroundColor: 'rgba(102,166,255,.10)', tension: .35 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cfe7f3' } } },
                    scales: {
                        x: { ticks: { color: '#9fb3c8' }, grid: { color: 'rgba(255,255,255,.06)' } },
                        y: { ticks: { color: '#9fb3c8' }, grid: { color: 'rgba(255,255,255,.06)' } }
                    }
                }
            });
        });
    </script>
@endpush
