<?php

namespace App\Services;

use App\Domain\Finance\FinancialMetrics;
use App\Support\FarmContext;
use App\Support\FarmFormat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RelatorioFinanceiroService
{
    public function __construct(private readonly FinancialMetrics $metrics) {}

    public function dre(array $filtros = []): array
    {
        $propertyId = $this->propertyId();
        $safras = $this->safrasDaPropriedade($propertyId);
        $filtros = $this->normalizarFiltrosDre($propertyId, $filtros);
        $receitas = $this->receitasPorCategoria($propertyId, $filtros);
        $despesasClassificadas = $this->despesasClassificadasDre($propertyId, $filtros);
        $custos = $despesasClassificadas['custos'];
        $despesas = $despesasClassificadas['despesas'];
        $totalReceitas = (float) $receitas->sum('valor_total');
        $totalCustos = (float) $custos->sum('valor_total');
        $totalDespesas = (float) $despesas->sum('valor_total');
        $resultado = $totalReceitas - $totalCustos - $totalDespesas;
        $margem = $this->metrics->percentage($resultado, $totalReceitas);
        $propertyName = (string) (DB::table('propriedades')->where('id', $propertyId)->value('nome') ?: 'Propriedade');
        $safrasSelecionadas = $safras->whereIn('id', $filtros['safra_ids'] ?? [])->values();
        $contextoSafras = $safrasSelecionadas->isNotEmpty()
            ? $safrasSelecionadas->pluck('descricao')->implode(' + ')
            : 'Periodo personalizado';
        $seletorSafras = $this->safraSelector($safras, $filtros['safra_ids'] ?? []);
        $receitas = $this->withRevenuePercentages($receitas, $totalReceitas);
        $custos = $this->withRevenuePercentages($custos, $totalReceitas);
        $despesas = $this->withRevenuePercentages($despesas, $totalReceitas);

        return [
            'activeModule' => 'financeiro',
            'title' => 'DRE',
            'subtitle' => 'Resumo de receitas, custos, despesas e resultado operacional da propriedade atual.',
            'cards' => [
                ['label' => 'Receitas', 'value' => FarmFormat::money($totalReceitas), 'tone' => 'success'],
                ['label' => 'Custos', 'value' => FarmFormat::money($totalCustos), 'tone' => 'warning'],
                ['label' => 'Despesas', 'value' => FarmFormat::money($totalDespesas), 'tone' => 'danger'],
                ['label' => 'Resultado', 'value' => FarmFormat::money($resultado), 'tone' => $resultado >= 0 ? 'success' : 'danger'],
                ['label' => 'Margem', 'value' => $totalReceitas > 0 ? number_format(($resultado / $totalReceitas) * 100, 2, ',', '.').'%' : '0,00%', 'tone' => $resultado >= 0 ? 'success' : 'danger'],
            ],
            'receitas' => $receitas,
            'custos' => $custos,
            'despesas' => $despesas,
            'filtros' => $filtros,
            'safras' => $safras,
            'propertyName' => $propertyName,
            'contextoSafras' => $contextoSafras,
            ...$seletorSafras,
            'resultClass' => $resultado >= 0 ? 'text-success' : 'text-danger',
            'resultLabel' => $resultado >= 0 ? 'Lucro no período' : 'Prejuízo no período',
            'resultTone' => $resultado >= 0 ? 'profit' : 'loss',
            'resultIcon' => $resultado >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow',
            'resultName' => $resultado >= 0 ? 'Lucro' : 'Prejuízo',
            'totais' => [
                'receitas' => $totalReceitas,
                'custos' => $totalCustos,
                'despesas' => $totalDespesas,
                'resultado' => $resultado,
                'margem' => $margem,
                'custos_percentual_receita' => $this->metrics->percentage($totalCustos, $totalReceitas),
                'despesas_percentual_receita' => $this->metrics->percentage($totalDespesas, $totalReceitas),
            ],
            'chart' => [
                'labels' => ['Receitas', 'Custos', 'Despesas', 'Resultado'],
                'values' => [$totalReceitas, $totalCustos, $totalDespesas, $resultado],
            ],
        ];
    }

    public function fluxoCaixa(array $filtros = []): array
    {
        $propertyId = $this->propertyId();
        $safras = $this->safrasDaPropriedade($propertyId);
        $filtros = $this->normalizarFiltrosFluxo($propertyId, $filtros);
        $rows = $this->fluxoMensal($propertyId, $filtros);
        $totalEntradas = (float) $rows->sum('receitas_valor');
        $totalSaidas = (float) $rows->sum('despesas_valor');
        $totalRecebido = (float) $rows->sum('recebido_valor');
        $totalPago = (float) $rows->sum('pago_valor');
        $saldoPrevisto = $totalEntradas - $totalSaidas;
        $saldoRealizado = $totalRecebido - $totalPago;
        $propertyName = (string) (DB::table('propriedades')->where('id', $propertyId)->value('nome') ?: 'Propriedade');
        $safrasSelecionadas = $safras->whereIn('id', $filtros['safra_ids'] ?? [])->values();
        $contextoSafras = $safrasSelecionadas->isNotEmpty()
            ? $safrasSelecionadas->pluck('descricao')->implode(' + ')
            : 'Periodo personalizado';
        $seletorSafras = $this->safraSelector($safras, $filtros['safra_ids'] ?? []);
        $rows = $rows->map(function ($row) {
            $row->saldo_previsto_classe = (float) $row->saldo_previsto_valor >= 0 ? 'text-success' : 'text-danger';
            $row->saldo_realizado_classe = (float) $row->saldo_realizado_valor >= 0 ? 'text-success' : 'text-danger';
            $row->acumulado_classe = (float) $row->acumulado_valor >= 0 ? 'text-success' : 'text-danger';

            return $row;
        });

        return [
            'activeModule' => 'financeiro',
            'title' => 'Fluxo de Caixa',
            'subtitle' => 'Entradas, saídas e saldo mensal com base nas receitas e despesas atuais.',
            'cards' => [
                ['label' => 'Entradas', 'value' => FarmFormat::money($totalEntradas), 'tone' => 'success'],
                ['label' => 'Saídas', 'value' => FarmFormat::money($totalSaidas), 'tone' => 'danger'],
                ['label' => 'Saldo', 'value' => FarmFormat::money($totalEntradas - $totalSaidas), 'tone' => $totalEntradas >= $totalSaidas ? 'success' : 'danger'],
                ['label' => 'Meses', 'value' => (string) $rows->count(), 'tone' => 'success'],
            ],
            'columns' => [
                'mes' => 'Mês',
                'entradas' => 'Entradas',
                'saidas' => 'Saídas',
                'saldo' => 'Saldo',
            ],
            'rows' => $rows,
            'subtitle' => 'Entradas, saidas, valores previstos e realizados por mes.',
            'cards' => [
                ['label' => 'Receitas previstas', 'value' => FarmFormat::money($totalEntradas), 'tone' => 'success'],
                ['label' => 'Despesas previstas', 'value' => FarmFormat::money($totalSaidas), 'tone' => 'danger'],
                ['label' => 'Saldo previsto', 'value' => FarmFormat::money($totalEntradas - $totalSaidas), 'tone' => $totalEntradas >= $totalSaidas ? 'success' : 'danger'],
                ['label' => 'Saldo realizado', 'value' => FarmFormat::money($totalRecebido - $totalPago), 'tone' => $totalRecebido >= $totalPago ? 'success' : 'danger'],
                ['label' => 'Meses', 'value' => (string) $rows->count(), 'tone' => 'success'],
            ],
            'columns' => [
                'mes' => 'Mes',
                'receitas' => 'Receitas previstas',
                'despesas' => 'Despesas previstas',
                'saldo_previsto' => 'Saldo previsto',
                'recebido' => 'Recebido',
                'pago' => 'Pago',
                'saldo_realizado' => 'Saldo realizado',
                'acumulado' => 'Acumulado',
            ],
            'filtros' => $filtros,
            'safras' => $safras,
            'propertyName' => $propertyName,
            'contextoSafras' => $contextoSafras,
            ...$seletorSafras,
            'totais' => [
                'receitas' => $totalEntradas,
                'despesas' => $totalSaidas,
                'recebido' => $totalRecebido,
                'pago' => $totalPago,
                'saldo_previsto' => $saldoPrevisto,
                'saldo_realizado' => $saldoRealizado,
                'saldo_previsto_classe' => $saldoPrevisto >= 0 ? 'text-success' : 'text-danger',
                'saldo_realizado_classe' => $saldoRealizado >= 0 ? 'text-success' : 'text-danger',
            ],
            'chart' => [
                'labels' => $rows->pluck('mes_label')->all(),
                'receitas' => $rows->pluck('receitas_valor')->all(),
                'despesas' => $rows->pluck('despesas_valor')->all(),
                'acumulado' => $rows->pluck('acumulado_valor')->all(),
            ],
        ];
    }

    public function orcadoRealizado(array $filtros = []): array
    {
        $propertyId = $this->propertyId();
        $filtros = $this->normalizarFiltrosOrcadoRealizado($propertyId, $filtros);
        $orcado = $this->projecoesPorCategoria($propertyId, $filtros);
        $realizado = $this->realizadoPorCategoria($propertyId, $filtros);
        $chartMensal = $this->orcadoRealizadoMensal($propertyId, $filtros);
        $categorias = collect();

        foreach ($orcado as $row) {
            $categorias[$row->categoria] = [
                'categoria' => $row->categoria,
                'orcado' => (float) $row->valor_total,
                'realizado' => 0.0,
            ];
        }

        foreach ($realizado as $row) {
            $item = $categorias[$row->categoria] ?? ['categoria' => $row->categoria, 'orcado' => 0.0, 'realizado' => 0.0];
            $item['realizado'] += (float) $row->valor_total;
            $categorias[$row->categoria] = $item;
        }

        $rows = $categorias
            ->map(function (array $row) {
                $diferenca = $row['realizado'] - $row['orcado'];
                $execucao = $row['orcado'] > 0 ? ($row['realizado'] / $row['orcado']) * 100 : 0;

                return (object) [
                    'categoria' => $row['categoria'],
                    'orcado_valor' => (float) $row['orcado'],
                    'realizado_valor' => (float) $row['realizado'],
                    'diferenca_valor' => (float) $diferenca,
                    'execucao_valor' => (float) $execucao,
                    'orcado' => FarmFormat::money($row['orcado']),
                    'realizado' => FarmFormat::money($row['realizado']),
                    'diferenca' => FarmFormat::money($diferenca),
                    'execucao' => number_format($execucao, 2, ',', '.').'%',
                    'peso' => abs($row['orcado']) + abs($row['realizado']),
                ];
            })
            ->sortByDesc('peso')
            ->values()
            ->map(function ($row) {
                unset($row->peso);

                return $row;
            });

        $totalOrcado = (float) $orcado->sum('valor_total');
        $totalRealizado = (float) $realizado->sum('valor_total');
        $diferenca = $totalRealizado - $totalOrcado;

        return [
            'activeModule' => 'financeiro',
            'title' => 'Orçado x Realizado',
            'subtitle' => 'Comparativo entre projeções financeiras, receitas e despesas já lançadas.',
            'propertyName' => (string) (DB::table('propriedades')->where('id', $propertyId)->value('nome') ?: 'Propriedade'),
            'filtros' => $filtros,
            'safras' => DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->orderByDesc('data_inicio')
                ->get(['id', 'descricao']),
            'categoriasFiltro' => DB::table('categorias')
                ->where('ativo', 1)
                ->whereNull('categoria_pai_id')
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'cards' => [
                ['label' => 'Orçado', 'value' => FarmFormat::money($totalOrcado), 'tone' => 'success'],
                ['label' => 'Realizado', 'value' => FarmFormat::money($totalRealizado), 'tone' => 'warning'],
                ['label' => 'Diferença', 'value' => FarmFormat::money($diferenca), 'tone' => $diferenca >= 0 ? 'success' : 'danger'],
                ['label' => 'Execução', 'value' => $totalOrcado > 0 ? number_format(($totalRealizado / $totalOrcado) * 100, 2, ',', '.').'%' : '0,00%', 'tone' => 'success'],
            ],
            'columns' => [
                'categoria' => 'Categoria',
                'orcado' => 'Orçado',
                'realizado' => 'Realizado',
                'diferenca' => 'Diferença',
                'execucao' => 'Execução',
            ],
            'rows' => $rows,
            'totais' => [
                'orcado' => $totalOrcado,
                'realizado' => $totalRealizado,
                'diferenca' => $diferenca,
                'execucao' => $totalOrcado > 0 ? ($totalRealizado / $totalOrcado) * 100 : 0,
            ],
            'chart' => [
                'labels' => $chartMensal->pluck('label')->all(),
                'orcado' => $chartMensal->pluck('orcado')->all(),
                'realizado' => $chartMensal->pluck('realizado')->all(),
            ],
        ];
    }

    public function categorias(?array $filtrosEntrada = null): array
    {
        $propertyId = $this->propertyId();
        $safras = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('data_inicio')
            ->get(['id', 'descricao', 'data_inicio']);

        $categoriasFiltro = DB::table('categorias')
            ->where('ativo', 1)
            ->whereNull('categoria_pai_id')
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $filtros = $this->filtrosCategorias($filtrosEntrada ?? [], $safras, $categoriasFiltro);

        $rows = $this->linhasCategorias($propertyId, $filtros);

        $total = (float) $rows->sum('total');
        $rows = $rows->map(function ($row) use ($total) {
            $row->percentual = $this->metrics->percentage($row->total, $total);
            $row->progresso_percentual = min(100.0, max(0.0, $row->percentual));

            return $row;
        });
        $tipoTotais = $rows->groupBy('tipo')->map(fn ($itens) => (float) $itens->sum('total'))->sortDesc();

        return [
            'activeModule' => 'relatorios',
            'title' => 'Relatório por Categoria',
            'subtitle' => 'Despesas aprovadas agrupadas por categoria e safra.',
            'safras' => $safras,
            'safraId' => $filtros['safra_id'],
            'filtros' => $filtros,
            'categoriasFiltro' => $categoriasFiltro,
            'talhoes' => DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'rows' => $rows,
            'chart' => [
                'categoria_labels' => $rows->pluck('nome')->values()->all(),
                'categoria_values' => $rows->pluck('total')->map(fn ($value) => (float) $value)->values()->all(),
                'categoria_colors' => $rows->pluck('cor')->map(fn ($color) => $color ?: '#35c49a')->values()->all(),
                'tipo_labels' => $tipoTotais->keys()->values()->all(),
                'tipo_values' => $tipoTotais->values()->all(),
            ],
            'cards' => [
                ['label' => 'Total', 'value' => FarmFormat::money($total), 'tone' => 'danger'],
                ['label' => $filtros['tipo'] === 'receitas' ? 'Recebido' : 'Pago', 'value' => FarmFormat::money((float) $rows->sum('pago')), 'tone' => 'success'],
                ['label' => 'Pendente', 'value' => FarmFormat::money((float) $rows->sum('pendente')), 'tone' => 'warning'],
                ['label' => 'Categorias', 'value' => (string) $rows->count(), 'tone' => 'success'],
            ],
        ];
    }

    private function filtrosCategorias(array $entrada, Collection $safras, Collection $categorias): array
    {
        $tipo = in_array($entrada['tipo'] ?? '', ['custos_despesas', 'despesas', 'receitas'], true)
            ? (string) $entrada['tipo']
            : 'custos_despesas';
        $safraId = (int) ($entrada['safra_id'] ?? 0);
        if ($safraId > 0 && ! $safras->contains('id', $safraId)) {
            $safraId = 0;
        }
        $categoriaId = (int) ($entrada['categoria_id'] ?? 0);
        if ($categoriaId > 0 && ! $categorias->contains('id', $categoriaId)) {
            $categoriaId = 0;
        }
        $inicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($entrada['data_inicio'] ?? '')) ? (string) $entrada['data_inicio'] : '';
        $fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($entrada['data_fim'] ?? '')) ? (string) $entrada['data_fim'] : '';
        if ($inicio !== '' && $fim !== '' && $fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return [
            'tipo' => $tipo,
            'safra_id' => $safraId,
            'categoria_id' => $categoriaId,
            'talhao_id' => $tipo === 'receitas' ? 0 : (int) ($entrada['talhao_id'] ?? 0),
            'data_inicio' => $inicio,
            'data_fim' => $fim,
        ];
    }

    private function linhasCategorias(int $propertyId, array $filtros): Collection
    {
        if ($filtros['tipo'] === 'receitas') {
            return DB::table('categorias as c')
                ->join('receitas as r', 'r.categoria_id', '=', 'c.id')
                ->where('r.propriedade_id', $propertyId)
                ->where('r.status', '!=', 'cancelado')
                ->when($filtros['safra_id'], fn ($query) => $query->where('r.safra_id', $filtros['safra_id']))
                ->when($filtros['categoria_id'], fn ($query) => $query->where('r.categoria_id', $filtros['categoria_id']))
                ->when($filtros['data_inicio'], fn ($query) => $query->whereRaw('COALESCE(r.data_recebimento, r.data_venda) >= ?', [$filtros['data_inicio']]))
                ->when($filtros['data_fim'], fn ($query) => $query->whereRaw('COALESCE(r.data_recebimento, r.data_venda) <= ?', [$filtros['data_fim']]))
                ->groupBy('c.id', 'c.nome', 'c.cor', 'c.tipo')
                ->orderByDesc(DB::raw('SUM(r.valor_total)'))
                ->get([
                    'c.nome',
                    'c.cor',
                    'c.tipo',
                    DB::raw('COUNT(r.id) as qtd'),
                    DB::raw('COALESCE(SUM(r.valor_total), 0) as total'),
                    DB::raw("COALESCE(SUM(CASE WHEN r.status = 'recebido' THEN r.valor_total ELSE 0 END), 0) as pago"),
                    DB::raw("COALESCE(SUM(CASE WHEN r.status != 'recebido' THEN r.valor_total ELSE 0 END), 0) as pendente"),
                ]);
        }

        return DB::table('categorias as c')
            ->join('despesas as d', 'd.categoria_id', '=', 'c.id')
            ->where('d.propriedade_id', $propertyId)
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(d.status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_id'], fn ($query) => $query->where('d.safra_id', $filtros['safra_id']))
            ->when($filtros['categoria_id'], fn ($query) => $query->where('d.categoria_id', $filtros['categoria_id']))
            ->when($filtros['talhao_id'], fn ($query) => $query->where('d.talhao_id', $filtros['talhao_id']))
            ->when($filtros['data_inicio'], fn ($query) => $query->whereRaw('COALESCE(d.data_pagamento, d.data_vencimento, d.data_lancamento) >= ?', [$filtros['data_inicio']]))
            ->when($filtros['data_fim'], fn ($query) => $query->whereRaw('COALESCE(d.data_pagamento, d.data_vencimento, d.data_lancamento) <= ?', [$filtros['data_fim']]))
            ->groupBy('c.id', 'c.nome', 'c.cor', 'c.tipo')
            ->orderByDesc(DB::raw('SUM(d.valor_total)'))
            ->get([
                'c.nome',
                'c.cor',
                'c.tipo',
                DB::raw('COUNT(d.id) as qtd'),
                DB::raw('COALESCE(SUM(d.valor_total), 0) as total'),
                DB::raw("COALESCE(SUM(CASE WHEN d.status_pagamento = 'pago' THEN d.valor_total ELSE 0 END), 0) as pago"),
                DB::raw("COALESCE(SUM(CASE WHEN d.status_pagamento = 'pendente' THEN d.valor_total ELSE 0 END), 0) as pendente"),
            ]);
    }

    public function safra(?int $safraId = null): array
    {
        $propertyId = $this->propertyId();
        $safras = $this->safrasDaPropriedade($propertyId);
        $safraId = $this->safraSelecionada($safras, $safraId);
        $safra = $safras->firstWhere('id', $safraId);

        $despesas = (float) DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('status_aprovacao', 'aprovada')
            ->sum('valor_total');

        $receitas = (float) DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->where('status', '!=', 'cancelado')
            ->sum('valor_total');

        $resultado = $receitas - $despesas;
        $area = (float) ($safra->area_plantada ?? 0);
        $estimada = $area * (float) ($safra->producao_estimada ?? 0);

        return [
            'activeModule' => 'relatorios',
            'title' => 'Relatório por Safra',
            'subtitle' => 'Resultado financeiro, área e produtividade estimada por safra.',
            'safras' => $safras,
            'safraId' => $safraId,
            'safra' => $safra,
            'mensal' => $this->despesasMensaisDaSafra($propertyId, $safraId),
            'categorias' => $this->despesasCategoriasDaSafra($propertyId, $safraId),
            'cards' => [
                ['label' => 'Despesas', 'value' => FarmFormat::money($despesas), 'tone' => 'danger'],
                ['label' => 'Receitas', 'value' => FarmFormat::money($receitas), 'tone' => 'success'],
                ['label' => 'Resultado', 'value' => FarmFormat::money($resultado), 'tone' => $resultado >= 0 ? 'success' : 'danger'],
                ['label' => 'ROI', 'value' => $despesas > 0 ? number_format(($resultado / $despesas) * 100, 1, ',', '.').'%' : '0,0%', 'tone' => $resultado >= 0 ? 'success' : 'danger'],
                ['label' => 'Área', 'value' => number_format($area, 2, ',', '.').' ha', 'tone' => 'success'],
                ['label' => 'Produção estimada', 'value' => number_format($estimada, 2, ',', '.').' sc', 'tone' => 'success'],
                ['label' => 'Custo/ha', 'value' => $area > 0 ? FarmFormat::money($despesas / $area) : 'R$ 0,00', 'tone' => 'warning'],
                ['label' => 'Receita/ha', 'value' => $area > 0 ? FarmFormat::money($receitas / $area) : 'R$ 0,00', 'tone' => 'success'],
                ['label' => 'Lucro/ha', 'value' => $area > 0 ? FarmFormat::money($resultado / $area) : 'R$ 0,00', 'tone' => $resultado >= 0 ? 'success' : 'danger'],
                ['label' => 'Margem liquida', 'value' => $receitas > 0 ? number_format(($resultado / $receitas) * 100, 1, ',', '.').'%' : '0,0%', 'tone' => $resultado >= 0 ? 'success' : 'danger'],
            ],
        ];
    }

    public function talhao(?int $safraId = null): array
    {
        $propertyId = $this->propertyId();
        $safras = $this->safrasDaPropriedade($propertyId);
        $safraId = $this->safraSelecionada($safras, $safraId);

        $rows = DB::table('talhoes as t')
            ->leftJoin('despesas as d', function ($join) use ($safraId) {
                $join->on('d.talhao_id', '=', 't.id')
                    ->where('d.safra_id', '=', $safraId)
                    ->where('d.status_pagamento', '!=', 'cancelado')
                    ->where('d.status_aprovacao', '=', 'aprovada');
            })
            ->where('t.propriedade_id', $propertyId)
            ->where('t.ativo', 1)
            ->groupBy('t.id', 't.nome', 't.area')
            ->orderByDesc(DB::raw('COALESCE(SUM(d.valor_total), 0)'))
            ->get([
                't.id',
                't.nome',
                't.area',
                DB::raw('COUNT(d.id) as qtd'),
                DB::raw('COALESCE(SUM(d.valor_total), 0) as total'),
            ])
            ->map(function ($row) {
                $row->custo_ha = $this->metrics->perUnit($row->total, $row->area);

                return $row;
            });

        $total = (float) $rows->sum('total');

        return [
            'activeModule' => 'relatorios',
            'title' => 'Relatório por Talhão',
            'subtitle' => 'Custos aprovados por talhão na safra selecionada.',
            'safras' => $safras,
            'safraId' => $safraId,
            'rows' => $rows,
            'chart' => [
                'labels' => $rows->pluck('nome')->values()->all(),
                'values' => $rows->pluck('custo_ha')->map(fn ($value) => (float) $value)->values()->all(),
            ],
            'cards' => [
                ['label' => 'Total', 'value' => FarmFormat::money($total), 'tone' => 'danger'],
                ['label' => 'Talhões', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Área total', 'value' => number_format((float) $rows->sum('area'), 2, ',', '.').' ha', 'tone' => 'success'],
                ['label' => 'Média R$/ha', 'value' => $rows->sum('area') > 0 ? FarmFormat::money($total / (float) $rows->sum('area')) : 'R$ 0,00', 'tone' => 'warning'],
            ],
        ];
    }

    public function kpis(?int $safraId = null): array
    {
        $propertyId = $this->propertyId();
        $safras = $this->safrasDaPropriedade($propertyId);
        $safraId = $this->safraSelecionada($safras, $safraId);
        $safra = $safras->firstWhere('id', $safraId);

        $totalDespesas = $safraId ? (float) DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('status_aprovacao', 'aprovada')
            ->sum('valor_total') : 0.0;

        $totalReceitas = $safraId ? (float) DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->where('status', '!=', 'cancelado')
            ->sum('valor_total') : 0.0;

        $area = (float) ($safra->area_plantada ?? 0);
        $producaoEstimada = (float) ($safra->producao_estimada ?? 0);
        $producaoRealizada = (float) ($safra->producao_realizada ?? 0);
        $precoEstimado = (float) ($safra->preco_estimado ?? 0);
        $producaoEstimadaTotal = $area * $producaoEstimada;
        $lucro = $totalReceitas - $totalDespesas;
        $roi = $totalDespesas > 0 ? ($lucro / $totalDespesas) * 100 : 0;
        $margem = $totalReceitas > 0 ? ($lucro / $totalReceitas) * 100 : 0;
        $produtividade = $area > 0 && $producaoRealizada > 0 ? $producaoRealizada / $area : $producaoEstimada;

        $comparativo = $this->comparativoSafras($propertyId);

        return [
            'activeModule' => 'relatorios',
            'title' => 'KPIs / ROI',
            'subtitle' => 'Indicadores de retorno, margem, custo e produtividade da safra selecionada.',
            'safras' => $safras,
            'safraId' => $safraId,
            'safra' => $safra,
            'comparativo' => $comparativo,
            'comparativoChart' => [
                'labels' => $comparativo->pluck('descricao')->values()->all(),
                'despesas' => $comparativo->pluck('total_despesas')->map(fn ($value) => (float) $value)->values()->all(),
                'receitas' => $comparativo->pluck('total_receitas')->map(fn ($value) => (float) $value)->values()->all(),
            ],
            'cards' => [
                ['label' => 'ROI', 'value' => number_format($roi, 1, ',', '.').'%', 'tone' => $roi >= 0 ? 'success' : 'danger'],
                ['label' => 'Margem liquida', 'value' => number_format($margem, 1, ',', '.').'%', 'tone' => $margem >= 0 ? 'success' : 'danger'],
                ['label' => 'Lucro', 'value' => FarmFormat::money($lucro), 'tone' => $lucro >= 0 ? 'success' : 'danger'],
                ['label' => 'Ponto de equilibrio', 'value' => $precoEstimado > 0 ? number_format($totalDespesas / $precoEstimado, 0, ',', '.').' sc' : '0 sc', 'tone' => 'warning'],
                ['label' => 'Custo por ha', 'value' => $area > 0 ? FarmFormat::money($totalDespesas / $area).'/ha' : 'R$ 0,00/ha', 'tone' => 'warning'],
                ['label' => 'Receita por ha', 'value' => $area > 0 ? FarmFormat::money($totalReceitas / $area).'/ha' : 'R$ 0,00/ha', 'tone' => 'success'],
                ['label' => 'Lucro por ha', 'value' => $area > 0 ? FarmFormat::money($lucro / $area).'/ha' : 'R$ 0,00/ha', 'tone' => $lucro >= 0 ? 'success' : 'danger'],
                ['label' => 'Custo por saca', 'value' => $this->custoPorSaca($totalDespesas, $producaoRealizada, $producaoEstimadaTotal), 'tone' => 'warning'],
                ['label' => 'Produtividade', 'value' => number_format($produtividade, 1, ',', '.').' sc/ha', 'tone' => 'success'],
                ['label' => 'Receita estimada', 'value' => FarmFormat::money($producaoEstimadaTotal * $precoEstimado), 'tone' => 'success'],
            ],
        ];
    }

    private function normalizarFiltrosDre(int $propertyId, array $filtros): array
    {
        $safraIds = collect($filtros['safras'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (! $safraIds && (int) ($filtros['safra_id'] ?? 0) > 0) {
            $safraIds = [(int) $filtros['safra_id']];
        }

        $dataInicio = $this->dataFiltro($filtros['data_inicio'] ?? null);
        $dataFim = $this->dataFiltro($filtros['data_fim'] ?? null);
        $periodoInformado = (bool) ($dataInicio || $dataFim);

        if ($periodoInformado && ! ($filtros['safras'] ?? []) && ! (int) ($filtros['safra_id'] ?? 0)) {
            $safraIds = [];
        } elseif ($safraIds) {
            $safraIds = DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->whereIn('id', $safraIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            $safraIds = DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($dataInicio && $dataFim && $dataFim < $dataInicio) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }

        return [
            'safra_id' => $safraIds[0] ?? null,
            'safra_ids' => $safraIds,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
    }

    private function receitasPorCategoria(int $propertyId, array $filtros = [])
    {
        return DB::table('receitas')
            ->leftJoin('categorias', 'categorias.id', '=', 'receitas.categoria_id')
            ->where('receitas.propriedade_id', $propertyId)
            ->where('receitas.status', '!=', 'cancelado')
            ->when($filtros['safra_ids'] ?? null, fn ($query, $safraIds) => $query->whereIn('receitas.safra_id', $safraIds))
            ->when($filtros['data_inicio'] ?? null, fn ($query, $data) => $query->where('receitas.data_venda', '>=', $data))
            ->when($filtros['data_fim'] ?? null, fn ($query, $data) => $query->where('receitas.data_venda', '<=', $data))
            ->groupBy('categorias.nome')
            ->orderByDesc(DB::raw('SUM(receitas.valor_total)'))
            ->get([
                DB::raw("COALESCE(categorias.nome, 'Sem categoria') as categoria"),
                DB::raw('SUM(receitas.valor_total) as valor_total'),
            ]);
    }

    private function despesasPorCategoria(int $propertyId, array $filtros = [])
    {
        return DB::table('despesas')
            ->leftJoin('categorias', 'categorias.id', '=', 'despesas.categoria_id')
            ->where('despesas.propriedade_id', $propertyId)
            ->where('despesas.status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(despesas.status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_ids'] ?? null, fn ($query, $safraIds) => $query->whereIn('despesas.safra_id', $safraIds))
            ->when($filtros['data_inicio'] ?? null, fn ($query, $data) => $query->where('despesas.data_lancamento', '>=', $data))
            ->when($filtros['data_fim'] ?? null, fn ($query, $data) => $query->where('despesas.data_lancamento', '<=', $data))
            ->groupBy('categorias.nome')
            ->orderByDesc(DB::raw('SUM(despesas.valor_total)'))
            ->get([
                DB::raw("COALESCE(categorias.nome, 'Sem categoria') as categoria"),
                DB::raw('SUM(despesas.valor_total) as valor_total'),
            ]);
    }

    private function despesasClassificadasDre(int $propertyId, array $filtros = []): array
    {
        $rows = DB::table('despesas')
            ->leftJoin('categorias', 'categorias.id', '=', 'despesas.categoria_id')
            ->where('despesas.propriedade_id', $propertyId)
            ->where('despesas.status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(despesas.status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_ids'] ?? null, fn ($query, $safraIds) => $query->whereIn('despesas.safra_id', $safraIds))
            ->when($filtros['data_inicio'] ?? null, fn ($query, $data) => $query->where('despesas.data_lancamento', '>=', $data))
            ->when($filtros['data_fim'] ?? null, fn ($query, $data) => $query->where('despesas.data_lancamento', '<=', $data))
            ->groupBy('categorias.id', 'categorias.nome')
            ->orderByDesc(DB::raw('SUM(despesas.valor_total)'))
            ->get([
                DB::raw('COALESCE(categorias.id, 0) as categoria_id'),
                DB::raw("COALESCE(categorias.nome, 'Sem categoria') as categoria"),
                DB::raw('SUM(despesas.valor_total) as valor_total'),
            ]);

        $custos = collect();
        $despesas = collect();

        foreach ($rows as $row) {
            $row->grupo_dre = $this->grupoDespesaDre((int) $row->categoria_id, (string) $row->categoria);
            if ($this->categoriaCustoDireto((int) $row->categoria_id, (string) $row->categoria)) {
                $custos->push($row);
            } else {
                $despesas->push($row);
            }
        }

        return [
            'custos' => $custos->values(),
            'despesas' => $despesas->values(),
        ];
    }

    private function categoriaCustoDireto(int $categoriaId, string $categoria): bool
    {
        $slug = $this->slugDre($categoria);

        return in_array($categoriaId, [1, 2, 6, 9, 10, 13, 27, 36, 98, 110, 114, 118, 121, 122, 124], true)
            || in_array($slug, [
                'sementes',
                'fertilizantes',
                'combustivel',
                'terceirizacoes agricolas',
                'arrendamento',
                'corretivos',
                'biologicos',
                'mao de obra',
                'colheita',
                'adjuvante',
                'frete',
                'nutricao de plantas',
                'oleo mineral vegetal',
                'lubrificantes',
                'quimico',
            ], true);
    }

    private function grupoDespesaDre(int $categoriaId, string $categoria): string
    {
        $slug = $this->slugDre($categoria);
        if (in_array($categoriaId, [100, 131, 137, 172], true) || in_array($slug, ['financeiro', 'financiamento', 'taxas e juros', 'emprestimo ao socio'], true)) {
            return 'Despesas financeiras';
        }

        if (in_array($categoriaId, [11, 85, 102, 107, 115, 116, 123], true) || in_array($slug, ['administrativo', 'equipamentos', 'retiradas', 'prolabore', 'seguro', 'energia', 'impostos'], true)) {
            return 'Administrativas';
        }

        return $this->categoriaCustoDireto($categoriaId, $categoria) ? $categoria : 'Operacionais';
    }

    private function slugDre(string $texto): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $base = $ascii !== false ? $ascii : $texto;

        return trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', ' ', $base)));
    }

    private function normalizarFiltrosFluxo(int $propertyId, array $filtros): array
    {
        $safraIds = collect($filtros['safras'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (! $safraIds && (int) ($filtros['safra_id'] ?? 0) > 0) {
            $safraIds = [(int) $filtros['safra_id']];
        }

        $dataInicio = $this->dataFiltro($filtros['data_inicio'] ?? null);
        $dataFim = $this->dataFiltro($filtros['data_fim'] ?? null);
        $periodoInformado = (bool) ($dataInicio || $dataFim);

        $safrasValidas = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->when($safraIds, fn ($query) => $query->whereIn('id', $safraIds))
            ->orderBy('data_inicio')
            ->get(['id', 'data_inicio', 'data_fim']);

        if ($periodoInformado && ! ($filtros['safras'] ?? []) && ! (int) ($filtros['safra_id'] ?? 0)) {
            $safraIds = [];
            $safrasValidas = collect();
        } elseif ($safraIds) {
            $safraIds = $safrasValidas->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $safrasValidas = DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->orderBy('data_inicio')
                ->get(['id', 'data_inicio', 'data_fim']);
            $safraIds = $safrasValidas->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $dataInicio && ! $dataFim && $safrasValidas->isNotEmpty()) {
            $dataInicio = (string) ($safrasValidas->min('data_inicio') ?: date('Y-01-01'));
            $dataFim = (string) ($safrasValidas
                ->map(fn ($safra) => $safra->data_fim ?: date('Y-m-t', strtotime((string) ($safra->data_inicio ?: date('Y-01-01')).' +11 months')))
                ->filter()
                ->max() ?: date('Y-m-t', strtotime($dataInicio.' +11 months')));
        } elseif (! $dataInicio && $dataFim) {
            $dataInicio = date('Y-m-d', strtotime($dataFim.' -11 months'));
        } elseif ($dataInicio && ! $dataFim) {
            $dataFim = date('Y-m-t', strtotime($dataInicio.' +11 months'));
        } elseif (! $dataInicio && ! $dataFim) {
            $dataInicio = date('Y-01-01');
            $dataFim = date('Y-12-31');
        }

        if ($dataInicio && $dataFim && $dataFim < $dataInicio) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }

        return [
            'safra_id' => $safraIds[0] ?? null,
            'safra_ids' => $safraIds,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
    }

    private function fluxoMensal(int $propertyId, array $filtros)
    {
        $periodoInicio = $filtros['data_inicio'];
        $periodoFim = $filtros['data_fim'];

        $despesasPrevistas = DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_ids'], fn ($query, $safraIds) => $query->whereIn('safra_id', $safraIds))
            ->whereRaw('COALESCE(data_vencimento, data_lancamento) BETWEEN ? AND ?', [$periodoInicio, $periodoFim])
            ->selectRaw("DATE_FORMAT(COALESCE(data_vencimento, data_lancamento), '%Y-%m') as mes, SUM(valor_total) as total")
            ->groupByRaw("DATE_FORMAT(COALESCE(data_vencimento, data_lancamento), '%Y-%m')")
            ->pluck('total', 'mes');

        $despesasPagas = DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('status_pagamento', 'pago')
            ->whereRaw("COALESCE(status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_ids'], fn ($query, $safraIds) => $query->whereIn('safra_id', $safraIds))
            ->whereRaw('COALESCE(data_pagamento, data_vencimento, data_lancamento) BETWEEN ? AND ?', [$periodoInicio, $periodoFim])
            ->selectRaw("DATE_FORMAT(COALESCE(data_pagamento, data_vencimento, data_lancamento), '%Y-%m') as mes, SUM(valor_total) as total")
            ->groupByRaw("DATE_FORMAT(COALESCE(data_pagamento, data_vencimento, data_lancamento), '%Y-%m')")
            ->pluck('total', 'mes');

        $receitasPrevistas = DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('status', '!=', 'cancelado')
            ->when($filtros['safra_ids'], fn ($query, $safraIds) => $query->whereIn('safra_id', $safraIds))
            ->whereRaw('COALESCE(data_recebimento, data_venda) BETWEEN ? AND ?', [$periodoInicio, $periodoFim])
            ->selectRaw("DATE_FORMAT(COALESCE(data_recebimento, data_venda), '%Y-%m') as mes, SUM(valor_total) as total")
            ->groupByRaw("DATE_FORMAT(COALESCE(data_recebimento, data_venda), '%Y-%m')")
            ->pluck('total', 'mes');

        $receitasRecebidas = DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('status', 'recebido')
            ->when($filtros['safra_ids'], fn ($query, $safraIds) => $query->whereIn('safra_id', $safraIds))
            ->whereRaw('COALESCE(data_recebimento, data_venda) BETWEEN ? AND ?', [$periodoInicio, $periodoFim])
            ->selectRaw("DATE_FORMAT(COALESCE(data_recebimento, data_venda), '%Y-%m') as mes, SUM(valor_total) as total")
            ->groupByRaw("DATE_FORMAT(COALESCE(data_recebimento, data_venda), '%Y-%m')")
            ->pluck('total', 'mes');

        $rows = collect();
        $acumulado = 0.0;
        $cursor = new \DateTime(date('Y-m-01', strtotime($periodoInicio)));
        $fim = new \DateTime(date('Y-m-01', strtotime($periodoFim)));

        while ($cursor <= $fim) {
            $mes = $cursor->format('Y-m');
            $receitas = (float) ($receitasPrevistas[$mes] ?? 0);
            $despesas = (float) ($despesasPrevistas[$mes] ?? 0);
            $recebido = (float) ($receitasRecebidas[$mes] ?? 0);
            $pago = (float) ($despesasPagas[$mes] ?? 0);
            $saldoRealizado = $recebido - $pago;
            $acumulado += $saldoRealizado;

            $rows->push((object) [
                'mes' => $cursor->format('Y-m'),
                'mes_label' => $this->mesAnoCurto($cursor),
                'receitas_valor' => $receitas,
                'despesas_valor' => $despesas,
                'recebido_valor' => $recebido,
                'pago_valor' => $pago,
                'saldo_previsto_valor' => $receitas - $despesas,
                'saldo_realizado_valor' => $saldoRealizado,
                'acumulado_valor' => $acumulado,
                'receitas' => FarmFormat::money($receitas),
                'despesas' => FarmFormat::money($despesas),
                'saldo_previsto' => FarmFormat::money($receitas - $despesas),
                'recebido' => FarmFormat::money($recebido),
                'pago' => FarmFormat::money($pago),
                'saldo_realizado' => FarmFormat::money($saldoRealizado),
                'acumulado' => FarmFormat::money($acumulado),
            ]);

            $cursor->modify('+1 month');
        }

        return $rows;
    }

    private function mesAnoCurto(\DateTimeInterface $data): string
    {
        $meses = [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Set',
            10 => 'Out',
            11 => 'Nov',
            12 => 'Dez',
        ];

        return ($meses[(int) $data->format('n')] ?? $data->format('M')).'/'.$data->format('y');
    }

    private function projecoesPorCategoria(int $propertyId, array $filtros)
    {
        return DB::table('financeiro_projecoes')
            ->leftJoin('categorias', 'categorias.id', '=', 'financeiro_projecoes.categoria_id')
            ->where('financeiro_projecoes.propriedade_id', $propertyId)
            ->when($filtros['safra_id'], fn ($query, $safraId) => $query->where('financeiro_projecoes.safra_id', $safraId))
            ->when($filtros['categoria_id'], fn ($query, $categoriaId) => $query->where('financeiro_projecoes.categoria_id', $categoriaId))
            ->when($filtros['data_inicio'], fn ($query, $data) => $query->where('financeiro_projecoes.mes_referencia', '>=', $data))
            ->when($filtros['data_fim'], fn ($query, $data) => $query->where('financeiro_projecoes.mes_referencia', '<=', $data))
            ->when($filtros['tipo'] !== 'todos', fn ($query) => $query->where('financeiro_projecoes.tipo_lancamento', $filtros['tipo'] === 'receita' ? 'receita' : 'despesa'))
            ->groupBy('categorias.nome')
            ->orderByDesc(DB::raw('SUM(financeiro_projecoes.valor_projetado)'))
            ->get([
                DB::raw("COALESCE(categorias.nome, 'Sem categoria') as categoria"),
                DB::raw('SUM(financeiro_projecoes.valor_projetado) as valor_total'),
            ]);
    }

    private function realizadoPorCategoria(int $propertyId, array $filtros)
    {
        $receitas = DB::table('receitas')
            ->leftJoin('categorias', 'categorias.id', '=', 'receitas.categoria_id')
            ->where('receitas.propriedade_id', $propertyId)
            ->where('receitas.status', '!=', 'cancelado')
            ->when($filtros['safra_id'], fn ($query, $safraId) => $query->where('receitas.safra_id', $safraId))
            ->when($filtros['categoria_id'], fn ($query, $categoriaId) => $query->where('receitas.categoria_id', $categoriaId))
            ->when($filtros['data_inicio'], fn ($query, $data) => $query->whereRaw('COALESCE(receitas.data_recebimento, receitas.data_venda) >= ?', [$data]))
            ->when($filtros['data_fim'], fn ($query, $data) => $query->whereRaw('COALESCE(receitas.data_recebimento, receitas.data_venda) <= ?', [$data]))
            ->groupBy('categorias.nome')
            ->select([
                DB::raw("COALESCE(categorias.nome, 'Sem categoria') as categoria"),
                DB::raw('SUM(receitas.valor_total) as valor_total'),
            ]);

        if ($filtros['tipo'] === 'receita') {
            return $receitas
                ->orderByDesc(DB::raw('SUM(receitas.valor_total)'))
                ->get();
        }

        $despesas = DB::table('despesas')
            ->leftJoin('categorias', 'categorias.id', '=', 'despesas.categoria_id')
            ->where('despesas.propriedade_id', $propertyId)
            ->where('despesas.status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(despesas.status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_id'], fn ($query, $safraId) => $query->where('despesas.safra_id', $safraId))
            ->when($filtros['categoria_id'], fn ($query, $categoriaId) => $query->where('despesas.categoria_id', $categoriaId))
            ->when($filtros['data_inicio'], fn ($query, $data) => $query->whereRaw('COALESCE(despesas.data_pagamento, despesas.data_vencimento, despesas.data_lancamento) >= ?', [$data]))
            ->when($filtros['data_fim'], fn ($query, $data) => $query->whereRaw('COALESCE(despesas.data_pagamento, despesas.data_vencimento, despesas.data_lancamento) <= ?', [$data]))
            ->groupBy('categorias.nome')
            ->select([
                DB::raw("COALESCE(categorias.nome, 'Sem categoria') as categoria"),
                DB::raw('SUM(despesas.valor_total) as valor_total'),
            ]);

        if ($filtros['tipo'] === 'custos_despesas') {
            return $despesas
                ->orderByDesc(DB::raw('SUM(despesas.valor_total)'))
                ->get();
        }

        return DB::query()
            ->fromSub(
                $despesas->unionAll($receitas),
                'realizado'
            )
            ->selectRaw('categoria, SUM(valor_total) as valor_total')
            ->groupBy('categoria')
            ->orderByDesc(DB::raw('SUM(valor_total)'))
            ->get();
    }

    private function orcadoRealizadoMensal(int $propertyId, array $filtros): Collection
    {
        $inicio = (string) $filtros['data_inicio'];
        $fim = (string) $filtros['data_fim'];
        $meses = collect();
        $cursor = strtotime(date('Y-m-01', strtotime($inicio)));
        $limite = strtotime(date('Y-m-01', strtotime($fim)));

        while ($cursor !== false && $limite !== false && $cursor <= $limite) {
            $key = date('Y-m', $cursor);
            $meses[$key] = (object) [
                'key' => $key,
                'label' => strtolower($this->mesAnoCurto(new \DateTimeImmutable(date('Y-m-01', $cursor)))),
                'orcado' => 0.0,
                'realizado' => 0.0,
            ];
            $cursor = strtotime('+1 month', $cursor);
        }

        $orcado = DB::table('financeiro_projecoes')
            ->where('financeiro_projecoes.propriedade_id', $propertyId)
            ->when($filtros['safra_id'], fn ($query, $safraId) => $query->where('financeiro_projecoes.safra_id', $safraId))
            ->when($filtros['categoria_id'], fn ($query, $categoriaId) => $query->where('financeiro_projecoes.categoria_id', $categoriaId))
            ->when($filtros['tipo'] !== 'todos', fn ($query) => $query->where('financeiro_projecoes.tipo_lancamento', $filtros['tipo'] === 'receita' ? 'receita' : 'despesa'))
            ->whereBetween('financeiro_projecoes.mes_referencia', [$inicio, $fim])
            ->selectRaw("DATE_FORMAT(financeiro_projecoes.mes_referencia, '%Y-%m') as mes, SUM(financeiro_projecoes.valor_projetado) as total")
            ->groupByRaw("DATE_FORMAT(financeiro_projecoes.mes_referencia, '%Y-%m')")
            ->pluck('total', 'mes');

        foreach ($orcado as $mes => $total) {
            if (isset($meses[$mes])) {
                $meses[$mes]->orcado = (float) $total;
            }
        }

        foreach ($this->realizadoMensal($propertyId, $filtros) as $mes => $total) {
            if (isset($meses[$mes])) {
                $meses[$mes]->realizado = (float) $total;
            }
        }

        return $meses->values();
    }

    private function realizadoMensal(int $propertyId, array $filtros): Collection
    {
        $inicio = (string) $filtros['data_inicio'];
        $fim = (string) $filtros['data_fim'];

        $receitas = DB::table('receitas')
            ->where('receitas.propriedade_id', $propertyId)
            ->where('receitas.status', '!=', 'cancelado')
            ->when($filtros['safra_id'], fn ($query, $safraId) => $query->where('receitas.safra_id', $safraId))
            ->when($filtros['categoria_id'], fn ($query, $categoriaId) => $query->where('receitas.categoria_id', $categoriaId))
            ->whereRaw('COALESCE(receitas.data_recebimento, receitas.data_venda) BETWEEN ? AND ?', [$inicio, $fim])
            ->selectRaw("DATE_FORMAT(COALESCE(receitas.data_recebimento, receitas.data_venda), '%Y-%m') as mes, SUM(receitas.valor_total) as valor_total")
            ->groupByRaw("DATE_FORMAT(COALESCE(receitas.data_recebimento, receitas.data_venda), '%Y-%m')");

        if ($filtros['tipo'] === 'receita') {
            return $receitas->pluck('valor_total', 'mes');
        }

        $despesas = DB::table('despesas')
            ->where('despesas.propriedade_id', $propertyId)
            ->where('despesas.status_pagamento', '!=', 'cancelado')
            ->whereRaw("COALESCE(despesas.status_aprovacao, '') != 'reprovada'")
            ->when($filtros['safra_id'], fn ($query, $safraId) => $query->where('despesas.safra_id', $safraId))
            ->when($filtros['categoria_id'], fn ($query, $categoriaId) => $query->where('despesas.categoria_id', $categoriaId))
            ->whereRaw('COALESCE(despesas.data_pagamento, despesas.data_vencimento, despesas.data_lancamento) BETWEEN ? AND ?', [$inicio, $fim])
            ->selectRaw("DATE_FORMAT(COALESCE(despesas.data_pagamento, despesas.data_vencimento, despesas.data_lancamento), '%Y-%m') as mes, SUM(despesas.valor_total) as valor_total")
            ->groupByRaw("DATE_FORMAT(COALESCE(despesas.data_pagamento, despesas.data_vencimento, despesas.data_lancamento), '%Y-%m')");

        if ($filtros['tipo'] === 'custos_despesas') {
            return $despesas->pluck('valor_total', 'mes');
        }

        return DB::query()
            ->fromSub(
                $despesas->unionAll($receitas),
                'realizado_mensal'
            )
            ->selectRaw('mes, SUM(valor_total) as valor_total')
            ->groupBy('mes')
            ->pluck('valor_total', 'mes');
    }

    private function normalizarFiltrosOrcadoRealizado(int $propertyId, array $filtros): array
    {
        $tipo = (string) ($filtros['tipo'] ?? 'todos');
        if (in_array($tipo, ['despesa', 'despesas'], true)) {
            $tipo = 'custos_despesas';
        }
        if (! in_array($tipo, ['todos', 'receita', 'custos_despesas'], true)) {
            $tipo = 'todos';
        }

        $safraId = (int) ($filtros['safra_id'] ?? 0);
        if ($safraId > 0 && ! DB::table('safras')->where('id', $safraId)->where('propriedade_id', $propertyId)->exists()) {
            $safraId = 0;
        }

        $categoriaId = (int) ($filtros['categoria_id'] ?? 0);
        if ($categoriaId > 0 && ! DB::table('categorias')->where('id', $categoriaId)->where('ativo', 1)->exists()) {
            $categoriaId = 0;
        }

        $dataInicio = $this->dataFiltro($filtros['data_inicio'] ?? null);
        $dataFim = $this->dataFiltro($filtros['data_fim'] ?? null);
        if (! $dataInicio && ! $dataFim) {
            $dataInicio = date('Y-01-01');
            $dataFim = date('Y-m-d');
        } elseif (! $dataInicio && $dataFim) {
            $dataInicio = date('Y-01-01', strtotime($dataFim));
        } elseif ($dataInicio && ! $dataFim) {
            $dataFim = date('Y-m-d');
        }
        if ($dataInicio && $dataFim && $dataFim < $dataInicio) {
            [$dataInicio, $dataFim] = [$dataFim, $dataInicio];
        }

        return [
            'safra_id' => $safraId ?: null,
            'categoria_id' => $categoriaId ?: null,
            'tipo' => $tipo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
    }

    private function dataFiltro($value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function propertyId(): int
    {
        return app(FarmContext::class)->propertyId();
    }

    /**
     * @param  Collection<int, object>  $safras
     * @param  array<int, int|string>  $selectedIds
     * @return array{selectedSafraIds: array<int, int>, allSafrasSelected: bool, safraButton: string}
     */
    private function safraSelector(Collection $safras, array $selectedIds): array
    {
        $selectedIds = collect($selectedIds)->map(fn ($id) => (int) $id)->values()->all();
        $allSelected = $selectedIds !== [] && count($selectedIds) === $safras->count();

        return [
            'selectedSafraIds' => $selectedIds,
            'allSafrasSelected' => $allSelected,
            'safraButton' => $allSelected || $selectedIds === []
                ? 'Todas as safras'
                : $safras->whereIn('id', $selectedIds)->pluck('descricao')->implode(' + '),
        ];
    }

    /** @param Collection<int, object> $rows */
    private function withRevenuePercentages(Collection $rows, float $totalRevenue): Collection
    {
        return $rows->map(function ($row) use ($totalRevenue) {
            $row->percentual_receita = $this->metrics->percentage($row->valor_total, $totalRevenue);

            return $row;
        });
    }

    private function safrasDaPropriedade(int $propertyId)
    {
        return DB::table('safras')
            ->leftJoin('culturas', 'culturas.id', '=', 'safras.cultura_id')
            ->where('safras.propriedade_id', $propertyId)
            ->orderByDesc('safras.data_inicio')
            ->get(['safras.*', 'culturas.nome as cultura_nome']);
    }

    private function safraSelecionada($safras, ?int $safraId): int
    {
        return $safraId && $safras->contains('id', $safraId)
            ? $safraId
            : (int) ($safras->first()->id ?? 0);
    }

    private function despesasMensaisDaSafra(int $propertyId, int $safraId)
    {
        return DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('status_aprovacao', 'aprovada')
            ->selectRaw("DATE_FORMAT(data_lancamento, '%Y-%m') as mes, COALESCE(SUM(valor_total), 0) as total")
            ->groupByRaw("DATE_FORMAT(data_lancamento, '%Y-%m')")
            ->orderBy('mes')
            ->get();
    }

    private function despesasCategoriasDaSafra(int $propertyId, int $safraId)
    {
        return DB::table('despesas as d')
            ->join('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->where('d.propriedade_id', $propertyId)
            ->where('d.safra_id', $safraId)
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->where('d.status_aprovacao', 'aprovada')
            ->groupBy('c.id', 'c.nome', 'c.cor')
            ->orderByDesc(DB::raw('SUM(d.valor_total)'))
            ->get(['c.nome', 'c.cor', DB::raw('COALESCE(SUM(d.valor_total), 0) as total')]);
    }

    private function comparativoSafras(int $propertyId)
    {
        return DB::table('safras as s')
            ->leftJoin('despesas as d', function ($join) {
                $join->on('d.safra_id', '=', 's.id')
                    ->where('d.status_pagamento', '!=', 'cancelado')
                    ->where('d.status_aprovacao', '=', 'aprovada');
            })
            ->where('s.propriedade_id', $propertyId)
            ->groupBy('s.id', 's.descricao', 's.area_plantada', 's.producao_estimada', 's.producao_realizada', 's.data_inicio')
            ->orderByDesc('s.data_inicio')
            ->limit(5)
            ->get([
                's.descricao',
                's.area_plantada',
                's.producao_estimada',
                's.producao_realizada',
                DB::raw('COALESCE(SUM(d.valor_total), 0) as total_despesas'),
                DB::raw("COALESCE((SELECT SUM(r.valor_total) FROM receitas r WHERE r.safra_id = s.id AND r.status != 'cancelado'), 0) as total_receitas"),
            ])
            ->map(function ($row) {
                $row->resultado = (float) $row->total_receitas - (float) $row->total_despesas;
                $row->result_tone = $row->resultado >= 0 ? 'success' : 'danger';

                return $row;
            });
    }

    private function custoPorSaca(float $totalDespesas, float $producaoRealizada, float $producaoEstimadaTotal): string
    {
        if ($producaoRealizada > 0) {
            return FarmFormat::money($totalDespesas / $producaoRealizada).'/sc';
        }

        if ($producaoEstimadaTotal > 0) {
            return FarmFormat::money($totalDespesas / $producaoEstimadaTotal).'/sc';
        }

        return 'R$ 0,00/sc';
    }
}
