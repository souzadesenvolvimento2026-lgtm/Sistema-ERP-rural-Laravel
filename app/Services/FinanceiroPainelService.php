<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceiroPainelService
{
    public function dados(int $propriedadeId, ?Request $request = null): array
    {
        $request ??= request();
        $filtros = $this->filtros($request);
        $periodo = $this->periodo($filtros);

        $despesas = $this->despesas($propriedadeId, $periodo);
        $receitas = $this->receitas($propriedadeId, $periodo);
        $transferencias = $this->transferencias($propriedadeId, $periodo);
        $lancamentos = $this->filtrarLancamentos($despesas, $receitas, $transferencias, $filtros);

        $totalDespesas = (float)$despesas->sum('valor');
        $totalReceitas = (float)$receitas->sum('valor');
        $saldoContas = $this->saldoContas($propriedadeId);
        $aPagar = (float)$despesas->whereIn('status', ['pendente', 'vencido'])->sum('valor');
        $aReceber = (float)$receitas->where('status', 'pendente')->sum('valor');

        return [
            'activeModule' => 'financeiro',
            'title' => 'Lançamentos financeiros',
            'subtitle' => 'Painel Financeiro',
            'periodoLabel' => $periodo['label'],
            'filtros' => $filtros,
            'cards' => [
                ['label' => 'Despesas gerais', 'value' => FarmFormat::money($totalDespesas), 'tone' => 'danger'],
                ['label' => 'Receitas gerais', 'value' => FarmFormat::money($totalReceitas), 'tone' => 'success'],
                ['label' => 'Resultado geral', 'value' => FarmFormat::money($totalReceitas - $totalDespesas), 'tone' => ($totalReceitas - $totalDespesas) >= 0 ? 'success' : 'danger'],
                ['label' => 'Saldo em contas', 'value' => FarmFormat::money($saldoContas), 'tone' => 'success'],
            ],
            'resumo' => [
                'despesas' => $totalDespesas,
                'receitas' => $totalReceitas,
                'resultado' => $totalReceitas - $totalDespesas,
                'saldoContas' => $saldoContas,
                'aPagar' => $aPagar,
                'aReceber' => $aReceber,
            ],
            'alertas' => [
                ['label' => 'Despesas pendentes', 'value' => (string)$despesas->whereIn('status', ['pendente', 'vencido'])->count()],
                ['label' => 'Agenda financeira', 'value' => FarmFormat::money($aPagar + $aReceber)],
                ['label' => 'Saldos por conta', 'value' => FarmFormat::money($saldoContas)],
            ],
            'contas' => $this->contas($propriedadeId),
            'agenda' => $this->agenda($despesas, $receitas),
            'categorias' => $this->categorias($propriedadeId),
            'lancamentos' => $lancamentos,
            'totalLancamentos' => $lancamentos->count(),
        ];
    }

    private function filtros(Request $request): array
    {
        return [
            'tipo' => in_array($request->query('filtro'), ['despesas', 'receitas', 'transferencias', 'pagar', 'receber'], true)
                ? (string)$request->query('filtro')
                : 'todos',
            'mes' => preg_match('/^\d{4}-\d{2}$/', (string)$request->query('mes')) ? (string)$request->query('mes') : date('Y-m'),
            'todos' => $request->boolean('todos'),
            'data_inicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('data_inicio')) ? (string)$request->query('data_inicio') : null,
            'data_fim' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('data_fim')) ? (string)$request->query('data_fim') : null,
        ];
    }

    private function periodo(array $filtros): array
    {
        if ($filtros['data_inicio'] && $filtros['data_fim']) {
            return [
                'inicio' => $filtros['data_inicio'],
                'fim' => $filtros['data_fim'],
                'label' => FarmFormat::date($filtros['data_inicio']).' a '.FarmFormat::date($filtros['data_fim']),
            ];
        }

        if ($filtros['todos']) {
            return ['inicio' => null, 'fim' => null, 'label' => 'Todos'];
        }

        return [
            'inicio' => $filtros['mes'].'-01',
            'fim' => date('Y-m-t', strtotime($filtros['mes'].'-01')),
            'label' => date('m/Y', strtotime($filtros['mes'].'-01')),
        ];
    }

    private function despesas(int $propriedadeId, array $periodo): Collection
    {
        if (!Schema::hasTable('despesas')) {
            return collect();
        }

        return DB::table('despesas as d')
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'd.safra_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'd.conta_id')
            ->where('d.propriedade_id', $propriedadeId)
            ->when($periodo['inicio'], fn ($query) => $query->whereBetween('d.data_lancamento', [$periodo['inicio'], $periodo['fim']]))
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->get([
                'd.id',
                'd.descricao',
                'd.fornecedor as pessoa',
                'd.valor_total as valor',
                'd.data_lancamento as data',
                'd.data_vencimento as previsto',
                'd.status_pagamento as status',
                'd.status_aprovacao',
                'c.nome as categoria',
                's.descricao as safra',
                'ct.nome as conta',
            ])
            ->map(fn ($row) => $this->normalizarLancamento($row, 'despesa'));
    }

    private function receitas(int $propriedadeId, array $periodo): Collection
    {
        if (!Schema::hasTable('receitas')) {
            return collect();
        }

        return DB::table('receitas as r')
            ->leftJoin('categorias as c', 'c.id', '=', 'r.categoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'r.safra_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'r.conta_id')
            ->where('r.propriedade_id', $propriedadeId)
            ->when($periodo['inicio'], fn ($query) => $query->whereBetween('r.data_venda', [$periodo['inicio'], $periodo['fim']]))
            ->where('r.status', '!=', 'cancelado')
            ->get([
                'r.id',
                'r.descricao',
                'r.comprador as pessoa',
                'r.valor_total as valor',
                'r.data_venda as data',
                'r.data_recebimento as previsto',
                'r.status',
                'r.status_aprovacao',
                'c.nome as categoria',
                's.descricao as safra',
                'ct.nome as conta',
            ])
            ->map(fn ($row) => $this->normalizarLancamento($row, 'receita'));
    }

    private function transferencias(int $propriedadeId, array $periodo): Collection
    {
        if (!Schema::hasTable('transferencias')) {
            return collect();
        }

        return DB::table('transferencias as t')
            ->leftJoin('contas as origem', 'origem.id', '=', 't.conta_origem_id')
            ->leftJoin('contas as destino', 'destino.id', '=', 't.conta_destino_id')
            ->where(function ($query) use ($propriedadeId) {
                $query->where('origem.propriedade_id', $propriedadeId)->orWhere('destino.propriedade_id', $propriedadeId);
            })
            ->when($periodo['inicio'], fn ($query) => $query->whereBetween('t.data_transferencia', [$periodo['inicio'], $periodo['fim']]))
            ->get([
                't.id',
                't.valor',
                't.data_transferencia as data',
                't.descricao as descricao',
                'origem.nome as conta_origem',
                'destino.nome as conta_destino',
            ])
            ->map(function ($row) {
                return (object)[
                    'id' => (int)$row->id,
                    'tipo' => 'transferencia',
                    'tipo_label' => 'Transferência',
                    'descricao' => $row->descricao ?: 'Transferência bancária',
                    'pessoa' => trim(($row->conta_origem ?: '-').' → '.($row->conta_destino ?: '-')),
                    'safra_categoria' => '-',
                    'conta' => $row->conta_origem ?: '-',
                    'valor' => (float)$row->valor,
                    'data' => $row->data,
                    'previsto' => $row->data,
                    'status' => 'transferido',
                    'status_aprovacao' => 'aprovada',
                ];
            });
    }

    private function normalizarLancamento(object $row, string $tipo): object
    {
        return (object)[
            'id' => (int)$row->id,
            'tipo' => $tipo,
            'tipo_label' => $tipo === 'receita' ? 'Receita' : 'Despesa',
            'descricao' => FarmFormat::value($row->descricao),
            'pessoa' => FarmFormat::value($row->pessoa),
            'safra_categoria' => trim(FarmFormat::value($row->safra).' / '.FarmFormat::value($row->categoria), ' /'),
            'conta' => FarmFormat::value($row->conta),
            'valor' => (float)$row->valor,
            'data' => $row->data,
            'previsto' => $row->previsto,
            'status' => (string)$row->status,
            'status_aprovacao' => (string)($row->status_aprovacao ?? 'aprovada'),
        ];
    }

    private function filtrarLancamentos(Collection $despesas, Collection $receitas, Collection $transferencias, array $filtros): Collection
    {
        $rows = match ($filtros['tipo']) {
            'despesas' => $despesas,
            'receitas' => $receitas,
            'transferencias' => $transferencias,
            'pagar' => $despesas->whereIn('status', ['pendente', 'vencido'])->values(),
            'receber' => $receitas->where('status', 'pendente')->values(),
            default => $despesas->concat($receitas)->concat($transferencias),
        };

        return $rows
            ->sortByDesc(fn ($row) => ($row->data ?: '').str_pad((string)$row->id, 8, '0', STR_PAD_LEFT))
            ->values();
    }

    private function saldoContas(int $propriedadeId): float
    {
        return (float)$this->contas($propriedadeId)->sum('saldo_numero');
    }

    private function contas(int $propriedadeId): Collection
    {
        if (!Schema::hasTable('contas')) {
            return collect();
        }

        return DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get()
            ->map(function ($row) {
                $saldo = (float)($row->saldo_inicial ?? 0);
                if (Schema::hasTable('receitas')) {
                    $saldo += (float)DB::table('receitas')->where('conta_id', $row->id)->where('status', 'recebido')->sum('valor_total');
                }
                if (Schema::hasTable('despesas')) {
                    $saldo -= (float)DB::table('despesas')->where('conta_id', $row->id)->where('status_pagamento', 'pago')->sum('valor_total');
                }
                if (Schema::hasTable('transferencias')) {
                    $saldo -= (float)DB::table('transferencias')->where('conta_origem_id', $row->id)->sum('valor');
                    $saldo += (float)DB::table('transferencias')->where('conta_destino_id', $row->id)->sum('valor');
                }

                return (object)[
                    'id' => (int)$row->id,
                    'nome' => FarmFormat::value($row->nome),
                    'detalhe' => FarmFormat::value($row->banco ?: FarmFormat::statusLabel((string)$row->tipo)),
                    'saldo_numero' => $saldo,
                    'saldo' => FarmFormat::money($saldo),
                ];
            })
            ->sortByDesc('saldo_numero')
            ->values();
    }

    private function agenda(Collection $despesas, Collection $receitas): Collection
    {
        return $despesas
            ->whereIn('status', ['pendente', 'vencido'])
            ->concat($receitas->where('status', 'pendente'))
            ->sortBy('previsto')
            ->take(8)
            ->values()
            ->map(fn ($row) => (object)[
                'tipo' => $row->tipo,
                'titulo' => $row->descricao,
                'pessoa' => $row->pessoa,
                'data' => FarmFormat::date($row->previsto),
                'valor' => FarmFormat::money($row->valor),
            ]);
    }

    private function categorias(int $propriedadeId): Collection
    {
        if (!Schema::hasTable('categorias') || !Schema::hasTable('despesas')) {
            return collect();
        }

        return DB::table('categorias as c')
            ->join('despesas as d', 'd.categoria_id', '=', 'c.id')
            ->where('d.propriedade_id', $propriedadeId)
            ->where('d.status_pagamento', '!=', 'cancelado')
            ->groupBy('c.id', 'c.nome')
            ->orderByDesc(DB::raw('COALESCE(SUM(d.valor_total),0)'))
            ->limit(7)
            ->get(['c.nome', DB::raw('COALESCE(SUM(d.valor_total),0) AS total')])
            ->map(fn ($row) => (object)[
                'nome' => FarmFormat::value($row->nome),
                'total' => FarmFormat::money($row->total),
            ]);
    }
}
