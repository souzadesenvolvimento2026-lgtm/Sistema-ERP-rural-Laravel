<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceiroPainelService
{
    private const MARCADOR_EXCLUSAO = '[FARMFLOW_SOLICITAR_EXCLUSAO]';

    public function dados(int $propriedadeId, ?Request $request = null): array
    {
        $request ??= request();
        $filtros = $this->filtros($request);
        $periodo = $this->periodo($filtros);
        $podeAprovar = $this->podeAprovar($propriedadeId, session('usuario_id'));

        $despesas = $this->despesas($propriedadeId, $periodo, $filtros);
        $receitas = $this->receitas($propriedadeId, $periodo, $filtros);
        $transferencias = $this->transferencias($propriedadeId, $periodo, $filtros);
        $lancamentos = $this->filtrarLancamentos($despesas, $receitas, $transferencias, $filtros);

        $totalDespesas = (float) $despesas->sum('valor');
        $totalReceitas = (float) $receitas->sum('valor');
        $saldoContas = $this->saldoContas($propriedadeId);
        $aPagar = (float) $despesas->whereIn('status', ['pendente', 'vencido'])->sum('valor');
        $aReceber = (float) $receitas->where('status', 'pendente')->sum('valor');

        return [
            'activeModule' => 'financeiro',
            'title' => 'Lançamentos financeiros',
            'subtitle' => $periodo['subtitle'],
            'periodoLabel' => $periodo['label'],
            'periodoScope' => $periodo['scope'],
            'filtros' => $filtros,
            'podeAprovarFinanceiro' => $podeAprovar,
            'cards' => [
                ['label' => 'Despesas '.$periodo['card_scope'], 'value' => FarmFormat::money($totalDespesas), 'tone' => 'danger'],
                ['label' => 'Receitas '.$periodo['card_scope'], 'value' => FarmFormat::money($totalReceitas), 'tone' => 'success'],
                ['label' => 'Resultado '.$periodo['result_scope'], 'value' => FarmFormat::money($totalReceitas - $totalDespesas), 'tone' => ($totalReceitas - $totalDespesas) >= 0 ? 'success' : 'danger'],
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
                ['label' => 'Despesas pendentes', 'value' => (string) $despesas->whereIn('status', ['pendente', 'vencido'])->count()],
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
        $inicio = $this->dateOrNull($request->query('data_inicio'));
        $fim = $this->dateOrNull($request->query('data_fim'));

        if ($inicio && $fim && $fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mes'))
            ? (string) $request->query('mes')
            : null;

        $contaId = (int) $request->query('conta_id', 0);
        $lancamentoId = (int) $request->query('lancamento_id', 0);

        return [
            'tipo' => in_array($request->query('filtro'), ['despesas', 'receitas', 'transferencias', 'pagar', 'receber', 'solicitacoes'], true)
                ? (string) $request->query('filtro')
                : 'todos',
            'mes' => $mes,
            'todos' => $request->boolean('todos') || (! $mes && ! $inicio && ! $fim),
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'search' => trim((string) $request->query('search', '')),
            'conta_id' => $contaId > 0 ? $contaId : null,
            'lancamento_id' => $lancamentoId > 0 ? $lancamentoId : null,
        ];
    }

    private function periodo(array $filtros): array
    {
        if ($filtros['data_inicio'] || $filtros['data_fim']) {
            $inicio = $filtros['data_inicio'] ?: $filtros['data_fim'];
            $fim = $filtros['data_fim'] ?: $filtros['data_inicio'];

            return [
                'inicio' => $inicio,
                'fim' => $fim,
                'label' => FarmFormat::date($inicio).' até '.FarmFormat::date($fim),
                'subtitle' => 'Resumo do período selecionado',
                'card_scope' => 'do período',
                'result_scope' => 'do período',
                'scope' => 'periodo',
            ];
        }

        if (! $filtros['todos'] && $filtros['mes']) {
            $inicio = $filtros['mes'].'-01';

            return [
                'inicio' => $inicio,
                'fim' => date('Y-m-t', strtotime($inicio)),
                'label' => date('m/Y', strtotime($inicio)),
                'subtitle' => 'Resumo do mês selecionado',
                'card_scope' => 'do mês',
                'result_scope' => 'do mês',
                'scope' => 'mes',
            ];
        }

        return [
            'inicio' => null,
            'fim' => null,
            'label' => 'Todos',
            'subtitle' => 'Resumo geral',
            'card_scope' => 'gerais',
            'result_scope' => 'geral',
            'scope' => 'todos',
        ];
    }

    private function despesas(int $propriedadeId, array $periodo, array $filtros): Collection
    {
        if (! Schema::hasTable('despesas')) {
            return collect();
        }

        $campoDataFiltro = in_array($filtros['tipo'], ['pagar'], true)
            ? DB::raw('COALESCE(d.data_vencimento, d.data_lancamento)')
            : DB::raw('COALESCE(d.data_lancamento, d.data_vencimento, d.data_pagamento)');

        return DB::table('despesas as d')
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('categorias as sc', 'sc.id', '=', 'd.subcategoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'd.safra_id')
            ->leftJoin('talhoes as t', 't.id', '=', 'd.talhao_id')
            ->leftJoin('produtores as p', 'p.id', '=', 'd.produtor_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'd.conta_id')
            ->where('d.propriedade_id', $propriedadeId)
            ->where(fn ($query) => $query->whereNull('d.status_pagamento')->orWhere('d.status_pagamento', '!=', 'cancelado'))
            ->when($periodo['inicio'], fn ($query) => $query->whereBetween($campoDataFiltro, [$periodo['inicio'], $periodo['fim']]))
            ->when($filtros['conta_id'], fn ($query, $contaId) => $query->where('d.conta_id', $contaId))
            ->get([
                'd.id',
                'd.descricao',
                'd.fornecedor',
                'd.valor_total as valor',
                'd.data_lancamento as data',
                'd.data_vencimento as previsto',
                'd.data_pagamento',
                'd.status_pagamento as status',
                'd.status_aprovacao',
                'd.motivo_reprovacao',
                'd.observacoes',
                'd.parcela_atual',
                'd.numero_parcelas',
                'c.nome as categoria',
                'sc.nome as subcategoria',
                's.descricao as safra',
                't.nome as talhao',
                'p.nome as produtor',
                'ct.nome as conta',
                'ct.banco as banco',
            ])
            ->map(fn ($row) => $this->normalizarDespesa($row));
    }

    private function receitas(int $propriedadeId, array $periodo, array $filtros): Collection
    {
        if (! Schema::hasTable('receitas')) {
            return collect();
        }

        $campoDataFiltro = in_array($filtros['tipo'], ['receber'], true)
            ? DB::raw('COALESCE(r.data_recebimento, r.data_venda)')
            : DB::raw('COALESCE(r.data_venda, r.data_recebimento)');

        return DB::table('receitas as r')
            ->leftJoin('categorias as c', 'c.id', '=', 'r.categoria_id')
            ->leftJoin('categorias as sc', 'sc.id', '=', 'r.subcategoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'r.safra_id')
            ->leftJoin('produtores as p', 'p.id', '=', 'r.produtor_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'r.conta_id')
            ->where('r.propriedade_id', $propriedadeId)
            ->where(fn ($query) => $query->whereNull('r.status')->orWhere('r.status', '!=', 'cancelado'))
            ->when($periodo['inicio'], fn ($query) => $query->whereBetween($campoDataFiltro, [$periodo['inicio'], $periodo['fim']]))
            ->when($filtros['conta_id'], fn ($query, $contaId) => $query->where('r.conta_id', $contaId))
            ->get([
                'r.id',
                'r.descricao',
                'r.comprador as pessoa',
                'r.valor_total as valor',
                'r.data_venda as data',
                'r.data_recebimento as previsto',
                'r.status',
                'r.status_aprovacao',
                'r.motivo_reprovacao',
                'r.observacoes',
                'c.nome as categoria',
                'sc.nome as subcategoria',
                's.descricao as safra',
                'p.nome as produtor',
                'ct.nome as conta',
                'ct.banco as banco',
            ])
            ->map(fn ($row) => $this->normalizarReceita($row));
    }

    private function transferencias(int $propriedadeId, array $periodo, array $filtros): Collection
    {
        if (! Schema::hasTable('transferencias')) {
            return collect();
        }

        return DB::table('transferencias as t')
            ->leftJoin('contas as origem', 'origem.id', '=', 't.conta_origem_id')
            ->leftJoin('contas as destino', 'destino.id', '=', 't.conta_destino_id')
            ->where(function ($query) use ($propriedadeId) {
                $query->where('origem.propriedade_id', $propriedadeId)
                    ->orWhere('destino.propriedade_id', $propriedadeId);
            })
            ->when($periodo['inicio'], fn ($query) => $query->whereBetween('t.data_transferencia', [$periodo['inicio'], $periodo['fim']]))
            ->when($filtros['conta_id'], function ($query, $contaId) {
                $query->where(fn ($subQuery) => $subQuery
                    ->where('t.conta_origem_id', $contaId)
                    ->orWhere('t.conta_destino_id', $contaId));
            })
            ->get([
                't.id',
                't.valor',
                't.data_transferencia as data',
                't.descricao',
                'origem.nome as conta_origem',
                'origem.banco as banco_origem',
                'destino.nome as conta_destino',
                'destino.banco as banco_destino',
            ])
            ->map(fn ($row) => $this->normalizarTransferencia($row));
    }

    private function normalizarDespesa(object $row): object
    {
        $status = (string) ($row->status ?: 'pendente');
        $approvalStatus = (string) ($row->status_aprovacao ?: 'aprovada');
        $temExclusao = $this->temSolicitacaoExclusao($row->observacoes ?? null);
        $previsto = $row->previsto ?: $row->data;

        if ($status !== 'pago' && $previsto && $previsto < Carbon::today()->toDateString()) {
            $status = 'vencido';
        }

        $categoria = $this->joinParts([$row->safra, $row->categoria, $row->subcategoria]);
        $conta = $this->joinParts([$row->conta, $row->banco], ' - ');
        $pessoa = $row->produtor ?: $row->fornecedor;
        $parcela = ((int) ($row->numero_parcelas ?? 1)) > 1
            ? ((int) ($row->parcela_atual ?? 1)).'/'.((int) $row->numero_parcelas)
            : null;
        $approvalDetail = $this->approvalDetail($approvalStatus, $temExclusao, $row->motivo_reprovacao);

        return (object) [
            'id' => (int) $row->id,
            'tipo' => 'despesa',
            'tipo_label' => 'Despesa',
            'type_tone' => 'ff-badge-expense',
            'descricao' => FarmFormat::value($row->descricao),
            'descricao_extra' => $parcela ? 'Parcela '.$parcela : null,
            'pessoa' => FarmFormat::value($pessoa),
            'pessoa_extra' => $row->fornecedor && $row->produtor ? FarmFormat::value($row->fornecedor) : null,
            'safra_categoria' => $categoria ?: '-',
            'conta' => $conta ?: '-',
            'valor' => (float) $row->valor,
            'data' => $row->data,
            'previsto' => $previsto,
            'status' => $status,
            'status_aprovacao' => $approvalStatus,
            'status_label' => $this->statusDespesaLabel($status, $previsto, $row->data_pagamento),
            'status_detail' => $approvalDetail,
            'workflow_detail' => $approvalDetail,
            'workflow_detail_tone' => $approvalStatus === 'reprovada' ? 'text-danger' : 'text-warning',
            'value_tone' => 'ff-value-expense',
            'status_tone' => match ($status) {
                'pago' => 'success',
                'vencido' => 'danger',
                default => 'warning',
            },
            'needs_approval' => $approvalStatus === 'pendente' || $temExclusao,
            'has_delete_request' => $temExclusao,
            'is_rejected' => $approvalStatus === 'reprovada',
            'is_overdue' => $status === 'vencido',
            'is_pending' => in_array($status, ['pendente', 'vencido'], true),
            'data_sort' => $row->data ?: $previsto,
            'action_url' => route('financeiro.despesas.edit', $row->id),
            'duplicate_url' => route('financeiro.despesas.duplicate', $row->id),
            'approve_url' => route('financeiro.despesas.approve', $row->id),
            'pay_url' => route('financeiro.despesas.pay', $row->id),
            'cancel_url' => route('financeiro.despesas.cancel', $row->id),
            'can_approve' => $approvalStatus === 'pendente' || $temExclusao,
            'can_pay' => $approvalStatus === 'aprovada' && in_array($status, ['pendente', 'vencido'], true),
        ];
    }

    private function normalizarReceita(object $row): object
    {
        $status = (string) ($row->status ?: 'pendente');
        $approvalStatus = (string) ($row->status_aprovacao ?: 'aprovada');
        $temExclusao = $this->temSolicitacaoExclusao($row->observacoes ?? null);
        $previsto = $row->previsto ?: $row->data;
        $categoria = $this->joinParts([$row->safra, $row->categoria, $row->subcategoria]);
        $conta = $this->joinParts([$row->conta, $row->banco], ' - ');
        $approvalDetail = $this->approvalDetail($approvalStatus, $temExclusao, $row->motivo_reprovacao);

        return (object) [
            'id' => (int) $row->id,
            'tipo' => 'receita',
            'tipo_label' => 'Receita',
            'type_tone' => 'ff-badge-income',
            'descricao' => FarmFormat::value($row->descricao),
            'descricao_extra' => null,
            'pessoa' => FarmFormat::value($row->pessoa),
            'pessoa_extra' => $row->produtor ? 'Produtor: '.FarmFormat::value($row->produtor) : null,
            'safra_categoria' => $categoria ?: '-',
            'conta' => $conta ?: '-',
            'valor' => (float) $row->valor,
            'data' => $row->data,
            'previsto' => $previsto,
            'status' => $status,
            'status_aprovacao' => $approvalStatus,
            'status_label' => $this->statusReceitaLabel($status, $previsto),
            'status_detail' => $approvalDetail,
            'workflow_detail' => $approvalDetail,
            'workflow_detail_tone' => $approvalStatus === 'reprovada' ? 'text-danger' : 'text-warning',
            'value_tone' => 'ff-value-income',
            'status_tone' => $status === 'recebido' ? 'success' : 'warning',
            'needs_approval' => $approvalStatus === 'pendente' || $temExclusao,
            'has_delete_request' => $temExclusao,
            'is_rejected' => $approvalStatus === 'reprovada',
            'is_overdue' => false,
            'is_pending' => $status === 'pendente',
            'data_sort' => $row->data ?: $previsto,
            'action_url' => route('financeiro.receitas.edit', $row->id),
            'duplicate_url' => route('financeiro.receitas.duplicate', $row->id),
            'approve_url' => route('financeiro.receitas.approve', $row->id),
            'receive_url' => route('financeiro.receitas.receive', $row->id),
            'cancel_url' => route('financeiro.receitas.cancel', $row->id),
            'can_approve' => $approvalStatus === 'pendente' || $temExclusao,
            'can_receive' => $approvalStatus === 'aprovada' && $status === 'pendente',
        ];
    }

    private function normalizarTransferencia(object $row): object
    {
        $origem = $this->joinParts([$row->conta_origem, $row->banco_origem], ' - ');
        $destino = $this->joinParts([$row->conta_destino, $row->banco_destino], ' - ');

        return (object) [
            'id' => (int) $row->id,
            'tipo' => 'transferencia',
            'tipo_label' => 'Transferência',
            'type_tone' => 'ff-badge-transfer',
            'descricao' => $row->descricao ?: 'Movimento entre contas',
            'descricao_extra' => 'Movimento entre contas',
            'pessoa' => 'Saiu de '.$origem,
            'pessoa_extra' => 'Entrou em '.$destino,
            'safra_categoria' => '-',
            'conta' => $origem ?: '-',
            'valor' => (float) $row->valor,
            'data' => $row->data,
            'previsto' => $row->data,
            'status' => 'transferido',
            'status_aprovacao' => 'aprovada',
            'status_label' => 'Transferida',
            'status_detail' => null,
            'workflow_detail' => null,
            'workflow_detail_tone' => 'text-warning',
            'value_tone' => 'ff-value-transfer',
            'status_tone' => 'success',
            'needs_approval' => false,
            'has_delete_request' => false,
            'is_rejected' => false,
            'is_overdue' => false,
            'is_pending' => false,
            'data_sort' => $row->data,
            'action_url' => route('financeiro.contas.index', [], false),
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
            'solicitacoes' => $despesas->concat($receitas)->filter(fn ($row) => $row->needs_approval)->values(),
            default => $despesas->concat($receitas)->concat($transferencias),
        };

        $highlightedExpenseId = (int) ($filtros['lancamento_id'] ?? 0);

        return $rows
            ->sort(function ($a, $b) use ($highlightedExpenseId) {
                if ($highlightedExpenseId > 0) {
                    $firstHighlighted = $a->tipo === 'despesa' && (int) $a->id === $highlightedExpenseId;
                    $secondHighlighted = $b->tipo === 'despesa' && (int) $b->id === $highlightedExpenseId;

                    if ($firstHighlighted !== $secondHighlighted) {
                        return $firstHighlighted ? -1 : 1;
                    }
                }

                if ($a->needs_approval !== $b->needs_approval) {
                    return $a->needs_approval ? -1 : 1;
                }

                $dateCompare = strcmp((string) ($b->data_sort ?? ''), (string) ($a->data_sort ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return ((int) $b->id) <=> ((int) $a->id);
            })
            ->values();
    }

    private function saldoContas(int $propriedadeId): float
    {
        return (float) $this->contas($propriedadeId)->sum('saldo_numero');
    }

    private function contas(int $propriedadeId): Collection
    {
        if (! Schema::hasTable('contas')) {
            return collect();
        }

        return DB::table('contas')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get()
            ->map(function ($row) {
                $saldo = (float) ($row->saldo_inicial ?? 0);
                if (Schema::hasTable('receitas')) {
                    $saldo += (float) DB::table('receitas')
                        ->where('conta_id', $row->id)
                        ->where('status', 'recebido')
                        ->sum('valor_total');
                }
                if (Schema::hasTable('despesas')) {
                    $saldo -= (float) DB::table('despesas')
                        ->where('conta_id', $row->id)
                        ->where('status_pagamento', 'pago')
                        ->sum('valor_total');
                }
                if (Schema::hasTable('transferencias')) {
                    $saldo -= (float) DB::table('transferencias')->where('conta_origem_id', $row->id)->sum('valor');
                    $saldo += (float) DB::table('transferencias')->where('conta_destino_id', $row->id)->sum('valor');
                }

                return (object) [
                    'id' => (int) $row->id,
                    'nome' => FarmFormat::value($row->nome),
                    'detalhe' => FarmFormat::value($row->banco ?: FarmFormat::statusLabel((string) $row->tipo)),
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
            ->map(fn ($row) => (object) [
                'tipo' => $row->tipo,
                'titulo' => $row->descricao,
                'pessoa' => $row->pessoa,
                'data' => FarmFormat::date($row->previsto),
                'valor' => FarmFormat::money($row->valor),
            ]);
    }

    private function categorias(int $propriedadeId): Collection
    {
        if (! Schema::hasTable('categorias') || ! Schema::hasTable('despesas')) {
            return collect();
        }

        return DB::table('categorias as c')
            ->join('despesas as d', 'd.categoria_id', '=', 'c.id')
            ->where('d.propriedade_id', $propriedadeId)
            ->where(fn ($query) => $query->whereNull('d.status_pagamento')->orWhere('d.status_pagamento', '!=', 'cancelado'))
            ->groupBy('c.id', 'c.nome')
            ->orderByDesc(DB::raw('COALESCE(SUM(d.valor_total),0)'))
            ->limit(7)
            ->get(['c.nome', DB::raw('COALESCE(SUM(d.valor_total),0) AS total')])
            ->map(fn ($row) => (object) [
                'nome' => FarmFormat::value($row->nome),
                'total' => FarmFormat::money($row->total),
            ]);
    }

    private function statusDespesaLabel(string $status, ?string $previsto, ?string $pagamento): string
    {
        if ($status === 'pago') {
            return 'Pago em '.FarmFormat::date($pagamento ?: $previsto);
        }

        if ($status === 'vencido') {
            return 'Venceu em '.FarmFormat::date($previsto);
        }

        return 'Vence em '.FarmFormat::date($previsto);
    }

    private function statusReceitaLabel(string $status, ?string $previsto): string
    {
        if ($status === 'recebido') {
            return 'Recebido em '.FarmFormat::date($previsto);
        }

        return 'Receber em '.FarmFormat::date($previsto);
    }

    private function approvalDetail(string $approvalStatus, bool $temExclusao, ?string $motivo): ?string
    {
        if ($temExclusao) {
            return 'Aguardando gestor para excluir';
        }

        if ($approvalStatus === 'pendente') {
            return 'Aguardando aprovação do gestor';
        }

        if ($approvalStatus === 'reprovada') {
            return trim('Reprovada'.($motivo ? ': '.$motivo : ''));
        }

        return null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function joinParts(array $parts, string $separator = ' / '): string
    {
        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode($separator);
    }

    private function temSolicitacaoExclusao(?string $observacoes): bool
    {
        return str_contains((string) $observacoes, self::MARCADOR_EXCLUSAO);
    }

    private function podeAprovar(int $propertyId, ?int $userId): bool
    {
        if (! $propertyId || ! $userId || ! Schema::hasTable('usuarios')) {
            return false;
        }

        $usuario = DB::table('usuarios')
            ->where('id', $userId)
            ->where('ativo', 1)
            ->first(['id', 'perfil']);

        if (! $usuario) {
            return false;
        }

        $perfil = (string) $usuario->perfil;
        if (in_array($perfil, ['administrador_sistema', 'gerencia_sistema'], true)) {
            return true;
        }

        if (! in_array($perfil, ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'gestao', 'financeiro'], true)) {
            return false;
        }

        if (! $this->usuarioAcessaPropriedade($propertyId, $userId, $perfil)) {
            return false;
        }

        if (in_array($perfil, ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'financeiro'], true)) {
            return true;
        }

        return (Schema::hasTable('propriedades') && DB::table('propriedades')
            ->where('id', $propertyId)
            ->where('ativo', 1)
            ->where('aprovador_usuario_id', $userId)
            ->exists())
            || (Schema::hasTable('grupos_fazendas') && Schema::hasTable('grupo_fazenda_propriedades') && DB::table('grupos_fazendas as gf')
                ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                ->where('gf.ativo', 1)
                ->where('gf.aprovador_usuario_id', $userId)
                ->where('gfp.propriedade_id', $propertyId)
                ->exists());
    }

    private function usuarioAcessaPropriedade(int $propertyId, int $userId, string $perfil): bool
    {
        if (in_array($perfil, ['administrador', 'administrador_sistema', 'gerencia_sistema'], true)) {
            return true;
        }

        return (Schema::hasTable('usuario_propriedades') && DB::table('usuario_propriedades')
            ->where('usuario_id', $userId)
            ->where('propriedade_id', $propertyId)
            ->exists())
            || (Schema::hasTable('usuario_grupos_fazendas') && Schema::hasTable('grupos_fazendas') && Schema::hasTable('grupo_fazenda_propriedades') && DB::table('usuario_grupos_fazendas as ugf')
                ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
                ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                ->where('gf.ativo', 1)
                ->where('ugf.usuario_id', $userId)
                ->where('gfp.propriedade_id', $propertyId)
                ->exists());
    }
}
