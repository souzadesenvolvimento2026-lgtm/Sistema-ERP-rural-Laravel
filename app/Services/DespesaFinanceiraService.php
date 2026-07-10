<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DespesaFinanceiraService
{
    public function pagina(int $propertyId, Request $request): array
    {
        $listas = $this->listas($propertyId);
        $filtros = $this->filtros($propertyId, $request, $listas);
        $rows = $this->rows($propertyId, $filtros);

        return [
            'activeModule' => 'financeiro',
            'title' => 'Despesas',
            'subtitle' => 'Consulta de despesas, vencimentos, pagamentos e aprovacoes financeiras.',
            'filtros' => $filtros,
            'safras' => $listas['safras'],
            'categorias' => $listas['categorias'],
            'contas' => $listas['contas'],
            'rows' => $rows,
            'cards' => [
                ['label' => 'Despesas', 'value' => FarmFormat::money($rows->sum('valor_raw')), 'tone' => 'danger'],
                ['label' => 'Pago', 'value' => FarmFormat::money($rows->where('status_key', 'pago')->sum('valor_raw')), 'tone' => 'success'],
                ['label' => 'A pagar', 'value' => FarmFormat::money($rows->whereIn('status_key', ['pendente', 'vencido'])->sum('valor_raw')), 'tone' => 'warning'],
                ['label' => 'Aguardando aprovacao', 'value' => (string)$rows->where('aprovacao_key', 'pendente')->count(), 'tone' => 'warning'],
            ],
            'statusOptions' => [
                '' => 'Todos',
                'pendente' => 'Pendente',
                'vencido' => 'Vencido',
                'pago' => 'Pago',
            ],
            'aprovacaoOptions' => [
                '' => 'Todas',
                'pendente' => 'Pendente',
                'aprovada' => 'Aprovada',
                'reprovada' => 'Reprovada',
            ],
        ];
    }

    public function aprovar(int $propertyId, int $despesaId, ?int $userId): void
    {
        if (!$this->podeAprovar($propertyId, $userId)) {
            throw new RuntimeException('Seu usuario nao tem permissao para aprovar despesas desta propriedade.');
        }

        DB::transaction(function () use ($propertyId, $despesaId, $userId): void {
            $despesa = DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->where('status_pagamento', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$despesa) {
                throw new RuntimeException('Despesa nao encontrada para aprovacao.');
            }

            $temSolicitacao = $this->temSolicitacaoExclusao($despesa->observacoes ?? null);
            if (($despesa->status_pagamento ?? '') === 'pago' && !$temSolicitacao) {
                throw new RuntimeException('Despesa paga nao pode ter a aprovacao alterada.');
            }

            if ($temSolicitacao) {
                $observacoes = $this->limparSolicitacaoExclusao($despesa->observacoes ?? null);
                DB::table('despesas')
                    ->where('id', $despesaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_pagamento' => 'cancelado',
                        'status_aprovacao' => 'aprovada',
                        'aprovado_por' => $userId,
                        'aprovado_em' => now(),
                        'motivo_reprovacao' => null,
                        'observacoes' => $observacoes ?: null,
                    ]);

                $this->auditar($userId, 'aprovar_exclusao_despesa', 'despesas', $despesaId, $propertyId, 'Exclusao de despesa aprovada');
                return;
            }

            DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status_aprovacao' => 'aprovada',
                    'aprovado_por' => $userId,
                    'aprovado_em' => now(),
                    'motivo_reprovacao' => null,
                ]);

            $this->auditar($userId, 'aprovar_despesa', 'despesas', $despesaId, $propertyId, 'Despesa aprovada');
        });
    }

    public function aprovarLote(int $propertyId, array $despesaIds, ?int $userId): array
    {
        if (!$this->podeAprovar($propertyId, $userId)) {
            throw new RuntimeException('Seu usuario nao tem permissao para aprovar despesas desta propriedade.');
        }

        $despesaIds = array_values(array_unique(array_filter(
            array_map(fn ($id) => (int)$id, $despesaIds),
            fn ($id) => $id > 0
        )));

        if (!$despesaIds) {
            throw new RuntimeException('Selecione ao menos uma despesa para aprovar.');
        }

        $aprovadas = 0;
        $ignoradas = 0;

        DB::transaction(function () use ($propertyId, $despesaIds, $userId, &$aprovadas, &$ignoradas): void {
            foreach ($despesaIds as $despesaId) {
                $despesa = DB::table('despesas')
                    ->where('id', $despesaId)
                    ->where('propriedade_id', $propertyId)
                    ->where('status_pagamento', '!=', 'cancelado')
                    ->where('status_aprovacao', 'pendente')
                    ->lockForUpdate()
                    ->first(['id', 'status_pagamento', 'observacoes']);

                $temSolicitacao = $despesa && $this->temSolicitacaoExclusao($despesa->observacoes ?? null);
                if (!$despesa || (($despesa->status_pagamento ?? '') === 'pago' && !$temSolicitacao)) {
                    $ignoradas++;
                    continue;
                }

                if ($temSolicitacao) {
                    $observacoes = $this->limparSolicitacaoExclusao($despesa->observacoes ?? null);
                    DB::table('despesas')
                        ->where('id', $despesaId)
                        ->where('propriedade_id', $propertyId)
                        ->update([
                            'status_pagamento' => 'cancelado',
                            'status_aprovacao' => 'aprovada',
                            'aprovado_por' => $userId,
                            'aprovado_em' => now(),
                            'motivo_reprovacao' => null,
                            'observacoes' => $observacoes ?: null,
                        ]);

                    $this->auditar($userId, 'aprovar_exclusao_despesa', 'despesas', $despesaId, $propertyId, 'Exclusao de despesa aprovada em lote');
                    $aprovadas++;
                    continue;
                }

                DB::table('despesas')
                    ->where('id', $despesaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_aprovacao' => 'aprovada',
                        'aprovado_por' => $userId,
                        'aprovado_em' => now(),
                        'motivo_reprovacao' => null,
                    ]);

                $this->auditar($userId, 'aprovar_despesa', 'despesas', $despesaId, $propertyId, 'Despesa aprovada em lote');
                $aprovadas++;
            }
        });

        return ['aprovadas' => $aprovadas, 'ignoradas' => $ignoradas];
    }

    public function reprovar(int $propertyId, int $despesaId, ?int $userId, ?string $motivo): void
    {
        if (!$this->podeAprovar($propertyId, $userId)) {
            throw new RuntimeException('Seu usuario nao tem permissao para aprovar despesas desta propriedade.');
        }

        DB::transaction(function () use ($propertyId, $despesaId, $userId, $motivo): void {
            $despesa = DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->where('status_pagamento', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$despesa) {
                throw new RuntimeException('Despesa nao encontrada para aprovacao.');
            }

            $temSolicitacao = $this->temSolicitacaoExclusao($despesa->observacoes ?? null);
            if (($despesa->status_pagamento ?? '') === 'pago' && !$temSolicitacao) {
                throw new RuntimeException('Despesa paga nao pode ter a aprovacao alterada.');
            }

            if ($temSolicitacao) {
                $observacoes = $this->limparSolicitacaoExclusao($despesa->observacoes ?? null);
                DB::table('despesas')
                    ->where('id', $despesaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_aprovacao' => 'aprovada',
                        'aprovado_por' => $userId,
                        'aprovado_em' => now(),
                        'motivo_reprovacao' => trim((string)$motivo) ?: null,
                        'observacoes' => $observacoes ?: null,
                    ]);

                $detalhes = 'Exclusao de despesa reprovada';
                if (trim((string)$motivo) !== '') {
                    $detalhes .= ' | Motivo: '.trim((string)$motivo);
                }

                $this->auditar($userId, 'reprovar_exclusao_despesa', 'despesas', $despesaId, $propertyId, $detalhes);
                return;
            }

            DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status_aprovacao' => 'reprovada',
                    'aprovado_por' => $userId,
                    'aprovado_em' => now(),
                    'motivo_reprovacao' => trim((string)$motivo) ?: null,
                ]);

            $detalhes = 'Despesa reprovada';
            if (trim((string)$motivo) !== '') {
                $detalhes .= ' | Motivo: '.trim((string)$motivo);
            }

            $this->auditar($userId, 'reprovar_despesa', 'despesas', $despesaId, $propertyId, $detalhes);
        });
    }

    public function pagar(int $propertyId, int $despesaId, ?int $contaId, ?string $dataPagamento, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $despesaId, $contaId, $dataPagamento, $userId): void {
            $despesa = DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->where('status_pagamento', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$despesa) {
                throw new RuntimeException('Despesa nao encontrada para pagamento.');
            }

            if (($despesa->status_aprovacao ?? '') !== 'aprovada') {
                throw new RuntimeException('Esta despesa precisa ser aprovada antes do pagamento.');
            }

            if ($contaId && !DB::table('contas')->where('id', $contaId)->where('propriedade_id', $propertyId)->exists()) {
                $contaId = null;
            }

            $dataEfetiva = $dataPagamento ?: now()->toDateString();

            DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status_pagamento' => 'pago',
                    'data_pagamento' => $dataEfetiva,
                    'conta_id' => $contaId,
                ]);

            $this->auditar($userId, 'pagar_despesa', 'despesas', $despesaId, $propertyId, 'Despesa paga | Data pagamento: '.$dataEfetiva);
        });
    }

    public function cancelar(int $propertyId, int $despesaId, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $despesaId, $userId): void {
            $despesa = DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->where('status_pagamento', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$despesa) {
                throw new RuntimeException('Despesa nao encontrada para exclusao.');
            }

            if (!$this->podeAprovar($propertyId, $userId)) {
                if ($this->temSolicitacaoExclusao($despesa->observacoes ?? null)) {
                    throw new RuntimeException('A exclusao desta despesa ja foi solicitada ao gestor.');
                }

                DB::table('despesas')
                    ->where('id', $despesaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_aprovacao' => 'pendente',
                        'aprovado_por' => null,
                        'aprovado_em' => null,
                        'motivo_reprovacao' => null,
                        'observacoes' => $this->adicionarSolicitacaoExclusao($despesa->observacoes ?? null, $userId),
                    ]);

                $this->auditar($userId, 'solicitar_exclusao_despesa', 'despesas', $despesaId, $propertyId, 'Exclusao de despesa solicitada');
                return;
            }

            $observacoes = $this->limparSolicitacaoExclusao($despesa->observacoes ?? null);
            $temSolicitacao = $this->temSolicitacaoExclusao($despesa->observacoes ?? null);

            DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status_pagamento' => 'cancelado',
                    'observacoes' => $observacoes ?: null,
                ]);

            $this->auditar(
                $userId,
                $temSolicitacao ? 'aprovar_exclusao_despesa' : 'cancelar_despesa',
                'despesas',
                $despesaId,
                $propertyId,
                $temSolicitacao ? 'Exclusao de despesa aprovada' : 'Despesa cancelada'
            );
        });
    }

    public function despesaParaEdicao(int $propertyId, int $despesaId): object
    {
        $despesa = DB::table('despesas')
            ->where('id', $despesaId)
            ->where('propriedade_id', $propertyId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->first();

        if (!$despesa) {
            throw new RuntimeException('Despesa nao encontrada para edicao.');
        }

        $despesa->tipo = 'despesa';
        $despesa->pessoa = $despesa->fornecedor;
        $despesa->preco_unitario = $despesa->valor_unitario;
        $despesa->data_vencimento = $despesa->data_vencimento ?: $despesa->data_pagamento;
        $despesa->baixado = ($despesa->status_pagamento ?? '') === 'pago' ? '1' : '0';

        return $despesa;
    }

    public function atualizar(int $propertyId, int $despesaId, array $dados, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $despesaId, $dados, $userId): void {
            $despesa = DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->where('status_pagamento', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$despesa) {
                throw new RuntimeException('Despesa nao encontrada para edicao.');
            }

            $quantidade = $this->decimal($dados['quantidade'] ?? 0);
            $valorUnitario = $this->decimal($dados['preco_unitario'] ?? 0);
            $valorTotal = $this->decimal($dados['valor_total'] ?? 0);
            if ($valorTotal <= 0 && $quantidade > 0 && $valorUnitario > 0) {
                $valorTotal = $quantidade * $valorUnitario;
            }

            $statusPagamento = ((string)($dados['baixado'] ?? '0')) === '1' ? 'pago' : 'pendente';
            $dataPagamento = $statusPagamento === 'pago'
                ? (($dados['data_vencimento'] ?? null) ?: ($dados['data_lancamento'] ?? now()->toDateString()))
                : null;

            DB::table('despesas')
                ->where('id', $despesaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propertyId),
                    'talhao_id' => $this->idDaPropriedade('talhoes', $dados['talhao_id'] ?? null, $propertyId),
                    'categoria_id' => (int)$dados['categoria_id'],
                    'subcategoria_id' => $this->subcategoriaId($dados['subcategoria_id'] ?? null, $dados['categoria_id'] ?? null),
                    'conta_id' => $this->idDaPropriedade('contas', $dados['conta_id'] ?? null, $propertyId),
                    'produtor_id' => $this->idDaPropriedade('produtores', $dados['produtor_id'] ?? null, $propertyId),
                    'descricao' => trim((string)$dados['descricao']),
                    'fornecedor' => trim((string)($dados['pessoa'] ?? '')) ?: null,
                    'quantidade' => $quantidade > 0 ? $quantidade : null,
                    'unidade' => $quantidade > 0 ? (trim((string)($dados['unidade'] ?? '')) ?: 'un') : null,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $valorTotal,
                    'data_lancamento' => $dados['data_lancamento'],
                    'data_vencimento' => ($dados['data_vencimento'] ?? null) ?: $dados['data_lancamento'],
                    'status_pagamento' => $statusPagamento,
                    'data_pagamento' => $dataPagamento,
                    'status_aprovacao' => 'aprovada',
                    'aprovado_por' => $userId,
                    'aprovado_em' => now(),
                    'motivo_reprovacao' => null,
                    'forma_pagamento' => ($dados['forma_pagamento'] ?? null) ?: ($despesa->forma_pagamento ?: 'pix'),
                    'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: null,
                    'usuario_id' => $userId ?: $despesa->usuario_id,
                ]);

            $this->auditar($userId, 'editar_despesa', 'despesas', $despesaId, $propertyId, 'Despesa editada pelo Laravel');
        });
    }

    private function listas(int $propertyId): array
    {
        return [
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'banco']),
        ];
    }

    private function filtros(int $propertyId, Request $request, array $listas): array
    {
        $safraId = $request->integer('safra_id') ?: null;
        if ($safraId && !$listas['safras']->contains('id', $safraId)) {
            $safraId = null;
        }

        $categoriaId = $request->integer('categoria_id') ?: null;
        if ($categoriaId && !$listas['categorias']->contains('id', $categoriaId)) {
            $categoriaId = null;
        }

        $contaId = $request->integer('conta_id') ?: null;
        if ($contaId && !$listas['contas']->contains('id', $contaId)) {
            $contaId = null;
        }

        return [
            'status' => in_array($request->query('status'), ['pendente', 'vencido', 'pago'], true) ? (string)$request->query('status') : '',
            'aprovacao' => in_array($request->query('aprovacao'), ['pendente', 'aprovada', 'reprovada'], true) ? (string)$request->query('aprovacao') : '',
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('date_from')) ? (string)$request->query('date_from') : '',
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('date_to')) ? (string)$request->query('date_to') : '',
            'safra_id' => $safraId,
            'categoria_id' => $categoriaId,
            'conta_id' => $contaId,
            'search' => trim((string)$request->query('search', '')),
        ];
    }

    private function rows(int $propertyId, array $filtros): Collection
    {
        $query = DB::table('despesas as d')
            ->leftJoin('safras as s', 's.id', '=', 'd.safra_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'd.categoria_id')
            ->leftJoin('categorias as sc', 'sc.id', '=', 'd.subcategoria_id')
            ->leftJoin('talhoes as t', 't.id', '=', 'd.talhao_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'd.conta_id')
            ->leftJoin('produtores as p', 'p.id', '=', 'd.produtor_id')
            ->where('d.propriedade_id', $propertyId)
            ->where('d.status_pagamento', '!=', 'cancelado');

        if ($filtros['status'] !== '') {
            $query->where('d.status_pagamento', $filtros['status']);
        }

        if ($filtros['aprovacao'] !== '') {
            $query->where('d.status_aprovacao', $filtros['aprovacao']);
        }

        if ($filtros['date_from'] !== '') {
            $query->whereDate('d.data_lancamento', '>=', $filtros['date_from']);
        }

        if ($filtros['date_to'] !== '') {
            $query->whereDate('d.data_lancamento', '<=', $filtros['date_to']);
        }

        if ($filtros['safra_id']) {
            $query->where('d.safra_id', $filtros['safra_id']);
        }

        if ($filtros['categoria_id']) {
            $query->where('d.categoria_id', $filtros['categoria_id']);
        }

        if ($filtros['conta_id']) {
            $query->where('d.conta_id', $filtros['conta_id']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('d.descricao', 'like', $term)
                    ->orWhere('d.fornecedor', 'like', $term)
                    ->orWhere('p.nome', 'like', $term)
                    ->orWhere('d.nota_fiscal', 'like', $term);
            });
        }

        return $query
            ->orderByRaw("CASE WHEN d.status_aprovacao = 'pendente' THEN 0 ELSE 1 END")
            ->orderByDesc('d.data_lancamento')
            ->orderByDesc('d.id')
            ->limit(240)
            ->get([
                'd.id',
                'd.descricao',
                'd.fornecedor',
                'd.quantidade',
                'd.unidade',
                'd.valor_unitario',
                'd.valor_total',
                'd.data_lancamento',
                'd.data_vencimento',
                'd.data_pagamento',
                'd.status_pagamento',
                'd.status_aprovacao',
                'd.forma_pagamento',
                'd.parcela_atual',
                'd.numero_parcelas',
                'd.nota_fiscal',
                'd.motivo_reprovacao',
                's.descricao as safra_nome',
                'c.nome as categoria_nome',
                'sc.nome as subcategoria_nome',
                't.nome as talhao_nome',
                'ct.nome as conta_nome',
                'ct.banco as conta_banco',
                'p.nome as produtor_nome',
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function normalizar($row): object
    {
        $categoria = trim((string)($row->categoria_nome ?? ''));
        if (!empty($row->subcategoria_nome)) {
            $categoria .= ($categoria ? ' / ' : '').$row->subcategoria_nome;
        }

        $conta = trim((string)($row->conta_nome ?? ''));
        if (!empty($row->conta_banco)) {
            $conta .= ($conta ? ' - ' : '').$row->conta_banco;
        }

        return (object)[
            'id' => (int)$row->id,
            'data_lancamento' => FarmFormat::date($row->data_lancamento),
            'descricao' => FarmFormat::value($row->descricao),
            'fornecedor' => FarmFormat::value($row->fornecedor),
            'categoria' => $categoria ?: '-',
            'safra' => FarmFormat::value($row->safra_nome),
            'talhao' => FarmFormat::value($row->talhao_nome),
            'produtor' => FarmFormat::value($row->produtor_nome),
            'quantidade' => $this->quantidade($row->quantidade, $row->unidade),
            'valor_unitario' => FarmFormat::money($row->valor_unitario),
            'valor_raw' => (float)$row->valor_total,
            'valor' => FarmFormat::money($row->valor_total),
            'vencimento' => FarmFormat::date($row->data_vencimento),
            'pagamento' => FarmFormat::date($row->data_pagamento),
            'conta' => $conta ?: '-',
            'forma_pagamento' => FarmFormat::value($row->forma_pagamento),
            'parcela' => $this->parcela($row->parcela_atual, $row->numero_parcelas),
            'nota_fiscal' => FarmFormat::value($row->nota_fiscal),
            'status_key' => (string)$row->status_pagamento,
            'status' => $this->labelStatus((string)$row->status_pagamento),
            'aprovacao_key' => (string)($row->status_aprovacao ?: 'aprovada'),
            'aprovacao' => $this->labelAprovacao((string)($row->status_aprovacao ?: 'aprovada')),
            'motivo_reprovacao' => FarmFormat::value($row->motivo_reprovacao),
        ];
    }

    private function quantidade($quantidade, ?string $unidade): string
    {
        if ($quantidade === null || (float)$quantidade == 0.0) {
            return '-';
        }

        return FarmFormat::decimal($quantidade, 3).' '.trim((string)$unidade);
    }

    private function parcela($atual, $total): string
    {
        $atual = max(1, (int)$atual);
        $total = max(1, (int)$total);

        return $atual.'/'.$total;
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'pago' => 'Pago',
            'vencido' => 'Vencido',
            'pendente' => 'Pendente',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function labelAprovacao(string $status): string
    {
        return match ($status) {
            'aprovada' => 'Aprovada',
            'pendente' => 'Pendente',
            'reprovada' => 'Reprovada',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function idDaPropriedade(string $table, mixed $id, int $propertyId): ?int
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return null;
        }

        return DB::table($table)
            ->where('id', $id)
            ->where('propriedade_id', $propertyId)
            ->exists() ? $id : null;
    }

    private function subcategoriaId(mixed $id, mixed $categoriaId): ?int
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return null;
        }

        $query = DB::table('categorias')
            ->where('id', $id)
            ->where('ativo', 1)
            ->whereNotNull('categoria_pai_id');

        $categoriaId = (int)($categoriaId ?? 0);
        if ($categoriaId > 0) {
            $query->where('categoria_pai_id', $categoriaId);
        }

        return $query->exists() ? $id : null;
    }

    private function marcadorExclusao(): string
    {
        return '[SOLICITACAO_EXCLUSAO_DESPESA]';
    }

    private function temSolicitacaoExclusao(?string $observacoes): bool
    {
        return str_contains((string)$observacoes, $this->marcadorExclusao());
    }

    private function adicionarSolicitacaoExclusao(?string $observacoes, ?int $userId): string
    {
        if ($this->temSolicitacaoExclusao($observacoes)) {
            return (string)$observacoes;
        }

        $usuario = $userId ? (string)DB::table('usuarios')->where('id', $userId)->value('nome') : 'colaborador';
        $linha = $this->marcadorExclusao().' Solicitado por '.($usuario ?: 'colaborador').' em '.now()->format('d/m/Y H:i');
        $observacoes = trim((string)$observacoes);

        return $observacoes === '' ? $linha : $observacoes.PHP_EOL.$linha;
    }

    private function limparSolicitacaoExclusao(?string $observacoes): string
    {
        $linhas = preg_split('/\r\n|\r|\n/', (string)$observacoes) ?: [];
        $linhas = array_filter($linhas, fn ($linha) => !str_contains((string)$linha, $this->marcadorExclusao()));

        return trim(implode(PHP_EOL, $linhas));
    }

    private function podeAprovar(int $propertyId, ?int $userId): bool
    {
        if (!$propertyId || !$userId) {
            return false;
        }

        $usuario = DB::table('usuarios')
            ->where('id', $userId)
            ->where('ativo', 1)
            ->first(['id', 'perfil']);

        if (!$usuario) {
            return false;
        }

        $perfil = (string)$usuario->perfil;
        if (in_array($perfil, ['administrador_sistema', 'gerencia_sistema'], true)) {
            return true;
        }

        if (!in_array($perfil, ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'gestao', 'financeiro'], true)) {
            return false;
        }

        if (!$this->usuarioAcessaPropriedade($propertyId, $userId, $perfil)) {
            return false;
        }

        if (in_array($perfil, ['administrador', 'gestor_financeiro', 'gestor_propriedade', 'financeiro'], true)) {
            return true;
        }

        return DB::table('propriedades')
            ->where('id', $propertyId)
            ->where('ativo', 1)
            ->where('aprovador_usuario_id', $userId)
            ->exists()
            || DB::table('grupos_fazendas as gf')
                ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                ->where('gf.ativo', 1)
                ->where('gf.aprovador_usuario_id', $userId)
                ->where('gfp.propriedade_id', $propertyId)
                ->exists();
    }

    private function usuarioAcessaPropriedade(int $propertyId, int $userId, string $perfil): bool
    {
        if (in_array($perfil, ['administrador', 'administrador_sistema', 'gerencia_sistema'], true)) {
            return true;
        }

        return DB::table('usuario_propriedades')
            ->where('usuario_id', $userId)
            ->where('propriedade_id', $propertyId)
            ->exists()
            || DB::table('usuario_grupos_fazendas as ugf')
                ->join('grupos_fazendas as gf', 'gf.id', '=', 'ugf.grupo_id')
                ->join('grupo_fazenda_propriedades as gfp', 'gfp.grupo_id', '=', 'gf.id')
                ->where('gf.ativo', 1)
                ->where('ugf.usuario_id', $userId)
                ->where('gfp.propriedade_id', $propertyId)
                ->exists();
    }

    private function decimal(mixed $value): float
    {
        $value = trim((string)$value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float)$value);
    }

    private function auditar(?int $usuarioId, string $acao, string $tabela, int $registroId, int $propriedadeId, string $detalhes): void
    {
        try {
            DB::table('logs_auditoria')->insert([
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'propriedade_id' => $propriedadeId,
                'detalhes' => $detalhes,
                'ip' => request()->ip(),
                'criado_em' => now(),
            ]);
        } catch (\Throwable) {
            // Auditoria nao deve impedir a operacao financeira.
        }
    }
}
