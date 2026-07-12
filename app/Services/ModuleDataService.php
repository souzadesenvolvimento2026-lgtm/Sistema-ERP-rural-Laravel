<?php

namespace App\Services;

use App\Domain\Finance\FinancialMetrics;
use App\Support\FarmContext;
use App\Support\FarmFormat;
use App\Support\ModuleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleDataService
{
    public function __construct(private readonly FinancialMetrics $metrics) {}

    public function dashboardData(): array
    {
        $propertyId = $this->propertyId();
        $property = $this->property();
        $safra = $this->activeSafra($propertyId);
        $safraId = $safra?->id;
        $year = (int) date('Y');

        $totalDespesas = $this->sumDespesaSafra($propertyId, $safraId);
        $despesasPagas = $this->sumDespesaSafra($propertyId, $safraId, 'pago');
        $despesasAPagar = max(0, $totalDespesas - $despesasPagas);
        $totalReceitas = $this->sumReceitaSafra($propertyId, $safraId);
        $receitasRecebidas = $this->sumReceitaSafra($propertyId, $safraId, 'recebido');
        $receitasAReceber = max(0, $totalReceitas - $receitasRecebidas);

        $areaPlantada = (float) ($safra?->area_plantada ?? 0);
        if ($areaPlantada <= 0) {
            $areaPlantada = $this->sumPropertySafe('talhoes', 'area', $propertyId, 'ativo=1');
        }

        $colheita = $this->colheitaResumo($propertyId, $safraId);
        $orcamento = $this->orcamentoResumo($propertyId, $safraId);
        $saldoSafra = $totalReceitas - $totalDespesas;
        $resultadoProjetado = $orcamento['receitaProjetada'] - $orcamento['despesaProjetada'];
        $cotacaoSoja = (float) ($property->cotacao_soja ?? 0);
        $budgetRows = $this->budgetRows($propertyId, $safraId);

        return [
            'activeModule' => 'dashboard',
            'property' => $property,
            'safra' => $safra,
            'year' => $year,
            'areaPlantada' => $areaPlantada,
            'cotacaoSoja' => $cotacaoSoja,
            'cotacaoUltima' => $property?->cotacao_soja_atualizada_em ? FarmFormat::date($property->cotacao_soja_atualizada_em) : 'Sem atualização',
            'totalDespesas' => $totalDespesas,
            'despesasPagas' => $despesasPagas,
            'despesasAPagar' => $despesasAPagar,
            'totalReceitas' => $totalReceitas,
            'receitasRecebidas' => $receitasRecebidas,
            'receitasAReceber' => $receitasAReceber,
            'saldoFinanceiro' => $receitasRecebidas - $despesasPagas,
            'saldoSafra' => $saldoSafra,
            'saldoContas' => $this->saldoContas($propertyId),
            'margem' => $totalReceitas > 0 ? ($saldoSafra / $totalReceitas) * 100 : 0,
            'custoHa' => $areaPlantada > 0 ? $totalDespesas / $areaPlantada : 0,
            'sacasColhidas' => $colheita['sacas'],
            'produtividade' => $colheita['area'] > 0 ? $colheita['sacas'] / $colheita['area'] : 0,
            'colheitaPct' => $areaPlantada > 0 ? min(100, ($colheita['area'] / $areaPlantada) * 100) : 0,
            'receitaProjetada' => $orcamento['receitaProjetada'],
            'despesaProjetada' => $orcamento['despesaProjetada'],
            'resultadoProjetado' => $resultadoProjetado,
            'desvioResultado' => $saldoSafra - $resultadoProjetado,
            'execucaoReceita' => $orcamento['receitaProjetada'] > 0 ? ($totalReceitas / $orcamento['receitaProjetada']) * 100 : 0,
            'execucaoDespesa' => $orcamento['despesaProjetada'] > 0 ? ($totalDespesas / $orcamento['despesaProjetada']) * 100 : 0,
            'execucaoResultado' => $resultadoProjetado != 0 ? ($saldoSafra / $resultadoProjetado) * 100 : 0,
            'custoSacasSojaTotal' => $cotacaoSoja > 0 ? $totalDespesas / $cotacaoSoja : 0,
            'maquinasAtivas' => $this->countWhere('maquinas', $propertyId, ['ativo' => 1]),
            'nfsMes' => $this->countMonth('nf_entradas', $propertyId, 'data_emissao'),
            'contratado' => $this->contratosResumo($propertyId, $safraId)['contratado'],
            'entregue' => $this->contratosResumo($propertyId, $safraId)['entregue'],
            'budgetRows' => $budgetRows,
            'talhoesResultado' => $this->talhoesResultado($propertyId, $safraId),
            'proximasAtividades' => $this->proximasAtividades($propertyId, $safraId),
            'receitasPorComprador' => $this->receitasPorComprador($propertyId, $safraId),
            'contasSaldoRows' => $this->contasSaldoRows($propertyId),
            'fluxo' => $this->fluxoCaixa($propertyId, $year),
            'dreLinhas' => [
                ['label' => 'Receita bruta', 'valor' => $totalReceitas, 'classe' => 'positive'],
                ['label' => 'Receitas recebidas', 'valor' => $receitasRecebidas, 'classe' => 'positive'],
                ['label' => 'Despesas e custos', 'valor' => -$totalDespesas, 'classe' => 'negative'],
                ['label' => 'Resultado da safra', 'valor' => $saldoSafra, 'classe' => $saldoSafra >= 0 ? 'positive' : 'negative'],
            ],
            'alertas' => $this->dashboardAlertas($budgetRows, $propertyId),
            'cards' => [
                ['label' => 'Despesas pagas', 'value' => $this->money($despesasPagas), 'tone' => 'danger'],
                ['label' => 'Receitas recebidas', 'value' => $this->money($receitasRecebidas), 'tone' => 'success'],
                ['label' => 'Pedidos fiscais', 'value' => (string) $this->countProperty('fiscal_orders', $propertyId), 'tone' => 'success'],
                ['label' => 'Patrimônios', 'value' => (string) $this->countProperty('maquinas', $propertyId), 'tone' => 'success'],
                ['label' => 'Talhões', 'value' => (string) $this->countProperty('talhoes', $propertyId), 'tone' => 'success'],
                ['label' => 'Produtos', 'value' => (string) $this->countProperty('produtos', $propertyId), 'tone' => 'success'],
                ['label' => 'Notas de entrada', 'value' => (string) $this->countProperty('nf_entradas', $propertyId), 'tone' => 'success'],
                ['label' => 'Colheitas', 'value' => (string) $this->countProperty('colheita_talhoes', $propertyId), 'tone' => 'success'],
            ],
            'recentExpenses' => $this->recentExpenses($propertyId),
            'recentOrders' => $this->recentOrders($propertyId),
        ];
    }

    public function moduleData(string $module): array
    {
        $propertyId = $this->propertyId();
        $config = ModuleCatalog::config($module);
        abort_if(! $config, 404);

        if (($config['type'] ?? '') === 'financeiro') {
            return $this->financeiroData($propertyId, $config);
        }

        if (($config['type'] ?? '') === 'fiscal') {
            return $this->fiscalData($propertyId, $config);
        }

        $query = DB::table($config['table']);
        if ($config['property_scoped'] ?? true) {
            $query->where('propriedade_id', $propertyId);
        }
        foreach ($config['joins'] ?? [] as $join) {
            $query->leftJoin($join[0], $join[1], '=', $join[2]);
        }

        return [
            'activeModule' => $module,
            'title' => $config['title'],
            'subtitle' => $config['subtitle'],
            'columns' => $config['columns'],
            'rows' => $query->select($config['select'])->orderByDesc($config['order'][0])->limit(80)->get(),
            'cards' => $this->moduleCards($module, $propertyId),
        ];
    }

    private function financeiroData(int $propertyId, array $config): array
    {
        $despesas = DB::table('despesas')
            ->leftJoin('categorias', 'categorias.id', '=', 'despesas.categoria_id')
            ->where('despesas.propriedade_id', $propertyId)
            ->selectRaw("'Despesa' as tipo, despesas.id, despesas.descricao, despesas.fornecedor as pessoa, categorias.nome as categoria, despesas.valor_total, despesas.data_lancamento as data_ref, despesas.status_pagamento as status")
            ->orderByDesc('data_ref')
            ->limit(60);

        return [
            'activeModule' => 'financeiro',
            'title' => $config['title'],
            'subtitle' => $config['subtitle'],
            'columns' => [
                'data_ref' => 'Data',
                'tipo' => 'Tipo',
                'descricao' => 'Descrição',
                'pessoa' => 'Pessoa',
                'categoria' => 'Categoria',
                'valor_total' => 'Valor',
                'status' => 'Status',
            ],
            'rows' => DB::table('receitas')
                ->leftJoin('categorias', 'categorias.id', '=', 'receitas.categoria_id')
                ->where('receitas.propriedade_id', $propertyId)
                ->selectRaw("'Receita' as tipo, receitas.id, receitas.descricao, receitas.comprador as pessoa, categorias.nome as categoria, receitas.valor_total, receitas.data_venda as data_ref, receitas.status")
                ->unionAll($despesas)
                ->orderByDesc('data_ref')
                ->limit(80)
                ->get(),
            'cards' => $this->moduleCards('financeiro', $propertyId),
        ];
    }

    private function fiscalData(int $propertyId, array $config): array
    {
        $entradas = DB::table('nf_entradas')
            ->where('propriedade_id', $propertyId)
            ->selectRaw("'Entrada NF' as tipo, id, numero, fornecedor as pessoa, fornecedor_doc as documento, valor_total, data_emissao as data_ref, status")
            ->orderByDesc('data_ref')
            ->limit(60);

        return [
            'activeModule' => 'fiscal',
            'title' => $config['title'],
            'subtitle' => $config['subtitle'],
            'columns' => [
                'data_ref' => 'Data',
                'tipo' => 'Tipo',
                'numero' => 'Número',
                'pessoa' => 'Pessoa',
                'documento' => 'Documento',
                'valor_total' => 'Valor',
                'status' => 'Status',
            ],
            'rows' => DB::table('notas_fiscais')
                ->where('propriedade_id', $propertyId)
                ->selectRaw("'Nota fiscal' as tipo, id, numero, emitente as pessoa, emitente_doc as documento, valor_total, data_emissao as data_ref, status")
                ->unionAll($entradas)
                ->orderByDesc('data_ref')
                ->limit(80)
                ->get(),
            'cards' => $this->moduleCards('fiscal', $propertyId),
        ];
    }

    private function activeSafra(int $propertyId): ?object
    {
        if (! Schema::hasTable('safras')) {
            return null;
        }

        return DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->whereIn('status', ['em_andamento', 'planejamento'])
            ->orderByRaw("CASE WHEN status='em_andamento' THEN 0 ELSE 1 END")
            ->orderByDesc('data_inicio')
            ->first()
            ?: DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->first();
    }

    private function sumDespesaSafra(int $propertyId, ?int $safraId, ?string $pagamento = null): float
    {
        if (! $safraId || ! Schema::hasTable('despesas')) {
            return 0;
        }

        return (float) DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->when($pagamento, fn ($query, $status) => $query->where('status_pagamento', $status))
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('status_aprovacao', 'aprovada')
            ->sum('valor_total');
    }

    private function sumReceitaSafra(int $propertyId, ?int $safraId, ?string $status = null): float
    {
        if (! $safraId || ! Schema::hasTable('receitas')) {
            return 0;
        }

        return (float) DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->where('status', '!=', 'cancelado')
            ->sum('valor_total');
    }

    private function colheitaResumo(int $propertyId, ?int $safraId): array
    {
        if (! $safraId || ! Schema::hasTable('colheita_talhoes')) {
            return ['sacas' => 0, 'area' => 0];
        }

        $row = DB::table('colheita_talhoes')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->selectRaw('COALESCE(SUM(sacas),0) as sacas, COALESCE(SUM(area_colhida),0) as area')
            ->first();

        return ['sacas' => (float) ($row->sacas ?? 0), 'area' => (float) ($row->area ?? 0)];
    }

    private function orcamentoResumo(int $propertyId, ?int $safraId): array
    {
        if (! $safraId || ! Schema::hasTable('financeiro_projecoes')) {
            return ['receitaProjetada' => 0, 'despesaProjetada' => 0];
        }

        $rows = DB::table('financeiro_projecoes')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->selectRaw('tipo_lancamento, COALESCE(SUM(valor_projetado),0) as total')
            ->groupBy('tipo_lancamento')
            ->pluck('total', 'tipo_lancamento');

        $despesa = (float) ($rows['despesa'] ?? 0);
        if ($despesa <= 0 && Schema::hasTable('orcamentos')) {
            $despesa = (float) DB::table('orcamentos')->where('safra_id', $safraId)->sum('valor_previsto');
        }

        return [
            'receitaProjetada' => (float) ($rows['receita'] ?? 0),
            'despesaProjetada' => $despesa,
        ];
    }

    private function fluxoCaixa(int $propertyId, int $year): array
    {
        $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $entradas = [];
        $saidas = [];
        $saldo = [];
        $acumulado = 0;

        foreach (range(1, 12) as $month) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));
            $entrada = $this->sumPeriodo('receitas', 'valor_total', 'data_venda', $propertyId, $start, $end, "status!='cancelado'");
            $saida = $this->sumPeriodo('despesas', 'valor_total', 'data_lancamento', $propertyId, $start, $end, "status_pagamento!='cancelado' AND status_aprovacao='aprovada'");
            $acumulado += $entrada - $saida;
            $entradas[] = round($entrada, 2);
            $saidas[] = round($saida, 2);
            $saldo[] = round($acumulado, 2);
        }

        return compact('labels', 'entradas', 'saidas', 'saldo');
    }

    private function budgetRows(int $propertyId, ?int $safraId)
    {
        if (! $safraId || ! Schema::hasTable('financeiro_projecoes') || ! Schema::hasTable('categorias')) {
            return collect();
        }

        $rows = DB::table('financeiro_projecoes as fp')
            ->join('categorias as c', 'c.id', '=', 'fp.categoria_id')
            ->where('fp.propriedade_id', $propertyId)
            ->where('fp.safra_id', $safraId)
            ->where('fp.tipo_lancamento', 'despesa')
            ->selectRaw('c.id, c.nome, c.cor, COALESCE(SUM(fp.valor_projetado),0) as valor_previsto')
            ->groupBy('c.id', 'c.nome', 'c.cor')
            ->orderByDesc('valor_previsto')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($propertyId, $safraId) {
                $row->realizado = Schema::hasTable('despesas')
                    ? (float) DB::table('despesas')
                        ->where('propriedade_id', $propertyId)
                        ->where('safra_id', $safraId)
                        ->where('categoria_id', $row->id)
                        ->where('status_pagamento', '!=', 'cancelado')
                        ->where('status_aprovacao', 'aprovada')
                        ->sum('valor_total')
                    : 0;

                return $this->withBudgetPerformance($row);
            });

        if ($rows->isNotEmpty() || ! Schema::hasTable('orcamentos')) {
            return $rows;
        }

        return DB::table('orcamentos as o')
            ->join('categorias as c', 'c.id', '=', 'o.categoria_id')
            ->where('o.safra_id', $safraId)
            ->selectRaw('c.id, c.nome, c.cor, o.valor_previsto')
            ->orderByDesc('o.valor_previsto')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($propertyId, $safraId) {
                $row->realizado = Schema::hasTable('despesas')
                    ? (float) DB::table('despesas')
                        ->where('propriedade_id', $propertyId)
                        ->where('safra_id', $safraId)
                        ->where('categoria_id', $row->id)
                        ->where('status_pagamento', '!=', 'cancelado')
                        ->where('status_aprovacao', 'aprovada')
                        ->sum('valor_total')
                    : 0;

                return $this->withBudgetPerformance($row);
            });
    }

    private function withBudgetPerformance(object $row): object
    {
        $performance = $this->metrics->budgetPerformance($row->valor_previsto, $row->realizado);
        $row->valor_previsto = $performance['planned'];
        $row->realizado = $performance['actual'];
        $row->desvio = $performance['variance'];
        $row->percentual_execucao = $performance['percentage'];
        $row->progresso_execucao = $performance['progress'];
        $row->desvio_classe = $performance['variance_tone'];

        return $row;
    }

    private function talhoesResultado(int $propertyId, ?int $safraId)
    {
        if (! $safraId || ! Schema::hasTable('talhoes') || ! Schema::hasTable('colheita_talhoes')) {
            return collect();
        }

        return DB::table('talhoes as t')
            ->leftJoin('colheita_talhoes as ct', function ($join) use ($safraId) {
                $join->on('ct.talhao_id', '=', 't.id')->where('ct.safra_id', $safraId);
            })
            ->where('t.propriedade_id', $propertyId)
            ->where('t.ativo', 1)
            ->selectRaw('t.nome, t.area, COALESCE(SUM(ct.sacas),0) as sacas, COALESCE(SUM(ct.area_colhida),0) as area_colhida')
            ->groupBy('t.id', 't.nome', 't.area')
            ->orderByDesc('sacas')
            ->limit(8)
            ->get();
    }

    private function proximasAtividades(int $propertyId, ?int $safraId)
    {
        if (! $safraId || ! Schema::hasTable('atividades_campo')) {
            return collect();
        }

        return DB::table('atividades_campo as a')
            ->leftJoin('talhoes as t', 't.id', '=', 'a.talhao_id')
            ->where('a.propriedade_id', $propertyId)
            ->where('a.safra_id', $safraId)
            ->whereIn('a.status', ['planejada', 'em_execucao'])
            ->orderBy('a.data_inicio')
            ->limit(6)
            ->get(['a.*', 't.nome as talhao_nome']);
    }

    private function receitasPorComprador(int $propertyId, ?int $safraId)
    {
        if (! $safraId || ! Schema::hasTable('receitas')) {
            return collect();
        }

        $normalizedReceitas = DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->where('status', '!=', 'cancelado')
            ->selectRaw("COALESCE(NULLIF(TRIM(comprador), ''), 'Sem comprador') as comprador_normalizado, valor_total");

        return DB::query()
            ->fromSub($normalizedReceitas, 'receitas_normalizadas')
            ->selectRaw('comprador_normalizado as nome, COALESCE(SUM(valor_total), 0) as total')
            ->groupBy('comprador_normalizado')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
    }

    private function contasSaldoRows(int $propertyId)
    {
        if (! Schema::hasTable('contas')) {
            return collect();
        }

        return DB::table('contas')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get()
            ->map(function ($conta) {
                $conta->saldo_atual = $this->saldoConta((int) $conta->id, (float) ($conta->saldo_inicial ?? 0));

                return $conta;
            })
            ->sortByDesc('saldo_atual')
            ->take(6)
            ->values();
    }

    private function contratosResumo(int $propertyId, ?int $safraId): array
    {
        if (! $safraId || ! Schema::hasTable('contratos')) {
            return ['contratado' => 0, 'entregue' => 0];
        }

        $contratos = DB::table('contratos')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraId)
            ->get(['id', 'quantidade']);
        $contratado = (float) $contratos->sum('quantidade');
        $entregue = 0;

        if (Schema::hasTable('contrato_entregas') && $contratos->isNotEmpty()) {
            $entregue = (float) DB::table('contrato_entregas')
                ->whereIn('contrato_id', $contratos->pluck('id')->all())
                ->sum('quantidade');
        }

        return ['contratado' => $contratado, 'entregue' => $entregue];
    }

    private function saldoContas(int $propertyId): float
    {
        return (float) $this->contasSaldoRows($propertyId)->sum('saldo_atual');
    }

    private function saldoConta(int $contaId, float $saldoInicial): float
    {
        $saldo = $saldoInicial;
        if (Schema::hasTable('receitas')) {
            $saldo += (float) DB::table('receitas')->where('conta_id', $contaId)->where('status', 'recebido')->sum('valor_total');
        }
        if (Schema::hasTable('despesas')) {
            $saldo -= (float) DB::table('despesas')->where('conta_id', $contaId)->where('status_pagamento', 'pago')->where('status_aprovacao', 'aprovada')->sum('valor_total');
        }
        if (Schema::hasTable('transferencias')) {
            $saldo -= (float) DB::table('transferencias')->where('conta_origem_id', $contaId)->sum('valor');
            $saldo += (float) DB::table('transferencias')->where('conta_destino_id', $contaId)->sum('valor');
        }

        return $saldo;
    }

    private function dashboardAlertas($budgetRows, int $propertyId): array
    {
        $alertas = [];
        foreach ($budgetRows as $row) {
            if ((float) $row->valor_previsto > 0 && (float) $row->realizado > (float) $row->valor_previsto) {
                $alertas[] = [
                    'icon' => 'bi-exclamation-triangle',
                    'title' => $row->nome.' estourou o orçamento',
                    'text' => FarmFormat::money($row->realizado).' realizado para '.FarmFormat::money($row->valor_previsto).' previsto',
                ];
            }
        }

        $qtdAprovacoes = Schema::hasTable('despesas')
            ? DB::table('despesas')->where('propriedade_id', $propertyId)->where('status_aprovacao', 'pendente')->count()
            : 0;
        if ($qtdAprovacoes > 0) {
            $alertas[] = ['icon' => 'bi-check2-square', 'title' => 'Despesas aguardando aprovação', 'text' => $qtdAprovacoes.' lançamento(s) precisam de gestor'];
        }

        return $alertas ?: [['icon' => 'bi-check-circle', 'title' => 'Operação sob controle', 'text' => 'Sem alertas críticos para a safra selecionada']];
    }

    private function recentExpenses(int $propertyId)
    {
        if (! Schema::hasTable('despesas')) {
            return collect();
        }

        return DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('data_lancamento')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'descricao', 'fornecedor', 'valor_total', 'data_lancamento', 'status_pagamento']);
    }

    private function recentOrders(int $propertyId)
    {
        if (! Schema::hasTable('fiscal_orders')) {
            return collect();
        }

        return DB::table('fiscal_orders')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'order_number', 'supplier_name', 'total_value', 'issue_date', 'status']);
    }

    private function moduleCards(string $module, int $propertyId): array
    {
        return match ($module) {
            'financeiro' => [
                ['label' => 'Despesas', 'value' => $this->money($this->sumProperty('despesas', 'valor_total', $propertyId, "status_pagamento!='cancelado'")), 'tone' => 'danger'],
                ['label' => 'Receitas', 'value' => $this->money($this->sumProperty('receitas', 'valor_total', $propertyId, "status!='cancelado'")), 'tone' => 'success'],
                ['label' => 'Pendentes', 'value' => (string) $this->countWhere('despesas', $propertyId, ['status_pagamento' => 'pendente']), 'tone' => 'warning'],
                ['label' => 'Pagas', 'value' => (string) $this->countWhere('despesas', $propertyId, ['status_pagamento' => 'pago']), 'tone' => 'success'],
            ],
            'fiscal' => [
                ['label' => 'Entradas NF', 'value' => (string) $this->countProperty('nf_entradas', $propertyId), 'tone' => 'success'],
                ['label' => 'Notas fiscais', 'value' => (string) $this->countProperty('notas_fiscais', $propertyId), 'tone' => 'success'],
                ['label' => 'Pedidos', 'value' => (string) $this->countProperty('fiscal_orders', $propertyId), 'tone' => 'success'],
                ['label' => 'Produtos fiscais', 'value' => (string) $this->countProperty('produtos', $propertyId), 'tone' => 'success'],
            ],
            default => [
                ['label' => 'Registros', 'value' => (string) $this->countProperty(ModuleCatalog::config($module)['table'], $propertyId, ModuleCatalog::config($module)['property_scoped'] ?? true), 'tone' => 'success'],
                ['label' => 'Propriedade', 'value' => $this->property()->nome ?? 'Atual', 'tone' => 'success'],
                ['label' => 'Status', 'value' => 'Migrado', 'tone' => 'success'],
                ['label' => 'Base', 'value' => 'Laravel', 'tone' => 'success'],
            ],
        };
    }

    private function property()
    {
        return app(FarmContext::class)->property();
    }

    private function propertyId(): int
    {
        return app(FarmContext::class)->propertyId();
    }

    private function countProperty(string $table, int $propertyId, bool $propertyScoped = true): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($propertyScoped && Schema::hasColumn($table, 'propriedade_id')) {
            $query->where('propriedade_id', $propertyId);
        }

        return (int) $query->count();
    }

    private function countWhere(string $table, int $propertyId, array $where): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if (Schema::hasColumn($table, 'propriedade_id')) {
            $query->where('propriedade_id', $propertyId);
        }
        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return (int) $query->count();
    }

    private function countMonth(string $table, int $propertyId, string $dateColumn): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $dateColumn)) {
            return 0;
        }

        return (int) DB::table($table)
            ->where('propriedade_id', $propertyId)
            ->whereBetween($dateColumn, [date('Y-m-01'), date('Y-m-t')])
            ->count();
    }

    private function sumProperty(string $table, string $column, int $propertyId, string $where = ''): float
    {
        return $this->sumPropertySafe($table, $column, $propertyId, $where);
    }

    private function sumPropertySafe(string $table, string $column, int $propertyId, string $where = ''): float
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $query = DB::table($table);
        if (Schema::hasColumn($table, 'propriedade_id')) {
            $query->where('propriedade_id', $propertyId);
        }
        if ($where !== '') {
            $query->whereRaw($where);
        }

        return (float) $query->sum($column);
    }

    private function sumPeriodo(string $table, string $column, string $dateColumn, int $propertyId, string $start, string $end, string $where = ''): float
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($table, $dateColumn)) {
            return 0;
        }

        $query = DB::table($table)->where('propriedade_id', $propertyId)->whereBetween($dateColumn, [$start, $end]);
        if ($where !== '') {
            $query->whereRaw($where);
        }

        return (float) $query->sum($column);
    }

    private function money(float $value): string
    {
        return FarmFormat::money($value);
    }
}
