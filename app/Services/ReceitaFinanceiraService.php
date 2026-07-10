<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReceitaFinanceiraService
{
    public function pagina(int $propertyId, Request $request): array
    {
        $this->importarCompradoresDeReceitas($propertyId);

        $filtros = $this->filtros($propertyId, $request);
        $rows = $this->rows($propertyId, $filtros);

        return [
            'activeModule' => 'financeiro',
            'title' => 'Receitas',
            'subtitle' => 'Consulta de receitas, recebimentos e aprovacoes financeiras da propriedade.',
            'filtros' => $filtros,
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'compradores' => $this->listarCompradores($propertyId),
            'rows' => $rows,
            'cards' => [
                ['label' => 'Receitas', 'value' => FarmFormat::money($rows->sum('valor_raw')), 'tone' => 'success'],
                ['label' => 'Recebido', 'value' => FarmFormat::money($rows->where('status_key', 'recebido')->sum('valor_raw')), 'tone' => 'success'],
                ['label' => 'Pendente', 'value' => FarmFormat::money($rows->where('status_key', 'pendente')->sum('valor_raw')), 'tone' => 'warning'],
                ['label' => 'Aguardando aprovacao', 'value' => (string)$rows->where('aprovacao_key', 'pendente')->count(), 'tone' => 'warning'],
            ],
            'statusOptions' => [
                '' => 'Todos',
                'pendente' => 'Pendente',
                'recebido' => 'Recebido',
            ],
            'aprovacaoOptions' => [
                '' => 'Todas',
                'pendente' => 'Pendente',
                'aprovada' => 'Aprovada',
                'reprovada' => 'Reprovada',
            ],
        ];
    }

    public function salvarComprador(int $propertyId, array $dados): int
    {
        $this->garantirEstruturaCompradores();

        $nome = $this->normalizarComprador((string)($dados['nome'] ?? ''));
        if ($nome === '') {
            throw new RuntimeException('Informe o nome do comprador.');
        }

        $documento = trim((string)($dados['documento'] ?? '')) ?: null;
        $existente = DB::table('compradores')
            ->where('propriedade_id', $propertyId)
            ->where('nome', $nome)
            ->first();

        if ($existente) {
            DB::table('compradores')
                ->where('id', $existente->id)
                ->update([
                    'documento' => $documento ?: $existente->documento,
                    'ativo' => 1,
                ]);

            return (int)$existente->id;
        }

        DB::table('compradores')->insert([
            'propriedade_id' => $propertyId,
            'nome' => $nome,
            'documento' => $documento,
            'ativo' => 1,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function listarCompradores(int $propertyId): Collection
    {
        $this->importarCompradoresDeReceitas($propertyId);

        return $this->compradores($propertyId);
    }

    public function receitaParaEdicao(int $propertyId, int $receitaId): object
    {
        $receita = DB::table('receitas')
            ->where('id', $receitaId)
            ->where('propriedade_id', $propertyId)
            ->where('status', '!=', 'cancelado')
            ->first();

        if (!$receita) {
            throw new RuntimeException('Receita nao encontrada para edicao.');
        }

        $receita->tipo = 'receita';
        $receita->pessoa = $receita->comprador;
        $receita->comprador_id = $this->compradorIdPorNome($propertyId, (string)($receita->comprador ?? ''));
        $receita->data_lancamento = $receita->data_venda;
        $receita->data_vencimento = $receita->data_recebimento;
        $receita->baixado = ($receita->status ?? '') === 'recebido' ? '1' : '0';

        return $receita;
    }

    public function atualizar(int $propertyId, int $receitaId, array $dados, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $receitaId, $dados, $userId): void {
            $receita = DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$receita) {
                throw new RuntimeException('Receita nao encontrada para edicao.');
            }

            $quantidade = $this->decimal($dados['quantidade'] ?? 0);
            $precoUnitario = $this->decimal($dados['preco_unitario'] ?? 0);
            $valorTotal = $this->decimal($dados['valor_total'] ?? 0);
            if ($valorTotal <= 0 && $quantidade > 0 && $precoUnitario > 0) {
                $valorTotal = $quantidade * $precoUnitario;
            }

            $status = ((string)($dados['baixado'] ?? '0')) === '1' ? 'recebido' : 'pendente';
            $dataRecebimento = $status === 'recebido'
                ? (($dados['data_vencimento'] ?? null) ?: ($dados['data_lancamento'] ?? now()->toDateString()))
                : null;

            $statusAprovacao = ($receita->status ?? '') === 'recebido'
                ? (($receita->status_aprovacao ?? '') ?: 'aprovada')
                : 'aprovada';

            DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propertyId),
                    'categoria_id' => ($dados['categoria_id'] ?? null) ?: null,
                    'subcategoria_id' => $this->subcategoriaId($dados['subcategoria_id'] ?? null, $dados['categoria_id'] ?? null),
                    'conta_id' => $this->idDaPropriedade('contas', $dados['conta_id'] ?? null, $propertyId),
                    'produtor_id' => $this->idDaPropriedade('produtores', $dados['produtor_id'] ?? null, $propertyId),
                    'descricao' => trim((string)$dados['descricao']),
                    'comprador' => $this->compradorNome($propertyId, $dados),
                    'quantidade' => $quantidade > 0 ? $quantidade : null,
                    'unidade' => $quantidade > 0 ? (trim((string)($dados['unidade'] ?? '')) ?: 'sc') : '',
                    'preco_unitario' => $precoUnitario,
                    'valor_total' => $valorTotal,
                    'data_venda' => $dados['data_lancamento'],
                    'data_recebimento' => $dataRecebimento,
                    'status' => $status,
                    'status_aprovacao' => $statusAprovacao,
                    'aprovado_por' => $userId,
                    'aprovado_em' => now(),
                    'motivo_reprovacao' => null,
                    'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: null,
                ]);

            $this->auditar($userId, 'editar_receita', 'receitas', $receitaId, $propertyId, 'Receita editada pelo Laravel');
        });
    }

    public function aprovar(int $propertyId, int $receitaId, ?int $userId): void
    {
        if (!$this->podeAprovar($propertyId, $userId)) {
            throw new RuntimeException('Seu usuario nao tem permissao para aprovar receitas desta propriedade.');
        }

        DB::transaction(function () use ($propertyId, $receitaId, $userId): void {
            $receita = DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$receita) {
                throw new RuntimeException('Receita nao encontrada para aprovacao.');
            }

            if (($receita->status ?? '') === 'recebido' && (($receita->status_aprovacao ?? '') !== 'pendente')) {
                throw new RuntimeException('Receita recebida nao pode ter a aprovacao alterada.');
            }

            if ($this->temSolicitacaoExclusao($receita->observacoes ?? null)) {
                $observacoes = $this->limparSolicitacaoExclusao($receita->observacoes ?? null);
                DB::table('receitas')
                    ->where('id', $receitaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status' => 'cancelado',
                        'status_aprovacao' => 'aprovada',
                        'aprovado_por' => $userId,
                        'aprovado_em' => now(),
                        'motivo_reprovacao' => null,
                        'observacoes' => $observacoes ?: null,
                    ]);

                $this->auditar($userId, 'aprovar_exclusao_receita', 'receitas', $receitaId, $propertyId, 'Exclusao de receita aprovada');
                return;
            }

            DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status_aprovacao' => 'aprovada',
                    'aprovado_por' => $userId,
                    'aprovado_em' => now(),
                    'motivo_reprovacao' => null,
                ]);

            $this->auditar($userId, 'aprovar_receita', 'receitas', $receitaId, $propertyId, 'Receita aprovada');
        });
    }

    public function aprovarLote(int $propertyId, array $receitaIds, ?int $userId): array
    {
        if (!$this->podeAprovar($propertyId, $userId)) {
            throw new RuntimeException('Seu usuario nao tem permissao para aprovar receitas desta propriedade.');
        }

        $receitaIds = array_values(array_unique(array_filter(
            array_map(fn ($id) => (int)$id, $receitaIds),
            fn ($id) => $id > 0
        )));

        if (!$receitaIds) {
            throw new RuntimeException('Selecione ao menos uma receita para aprovar.');
        }

        $aprovadas = 0;
        $ignoradas = 0;

        DB::transaction(function () use ($propertyId, $receitaIds, $userId, &$aprovadas, &$ignoradas): void {
            foreach ($receitaIds as $receitaId) {
                $receita = DB::table('receitas')
                    ->where('id', $receitaId)
                    ->where('propriedade_id', $propertyId)
                    ->where('status', '!=', 'cancelado')
                    ->where('status_aprovacao', 'pendente')
                    ->lockForUpdate()
                    ->first(['id', 'status']);

                if (!$receita || ($receita->status ?? '') === 'recebido') {
                    $ignoradas++;
                    continue;
                }

                DB::table('receitas')
                    ->where('id', $receitaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_aprovacao' => 'aprovada',
                        'aprovado_por' => $userId,
                        'aprovado_em' => now(),
                        'motivo_reprovacao' => null,
                    ]);

                $this->auditar($userId, 'aprovar_receita', 'receitas', $receitaId, $propertyId, 'Receita aprovada em lote');
                $aprovadas++;
            }
        });

        return ['aprovadas' => $aprovadas, 'ignoradas' => $ignoradas];
    }

    public function reprovar(int $propertyId, int $receitaId, ?int $userId, ?string $motivo): void
    {
        if (!$this->podeAprovar($propertyId, $userId)) {
            throw new RuntimeException('Seu usuario nao tem permissao para aprovar receitas desta propriedade.');
        }

        DB::transaction(function () use ($propertyId, $receitaId, $userId, $motivo): void {
            $receita = DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$receita) {
                throw new RuntimeException('Receita nao encontrada para aprovacao.');
            }

            if (($receita->status ?? '') === 'recebido' && (($receita->status_aprovacao ?? '') !== 'pendente')) {
                throw new RuntimeException('Receita recebida nao pode ter a aprovacao alterada.');
            }

            if ($this->temSolicitacaoExclusao($receita->observacoes ?? null)) {
                $observacoes = $this->limparSolicitacaoExclusao($receita->observacoes ?? null);
                DB::table('receitas')
                    ->where('id', $receitaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_aprovacao' => 'aprovada',
                        'aprovado_por' => $userId,
                        'aprovado_em' => now(),
                        'motivo_reprovacao' => null,
                        'observacoes' => $observacoes ?: null,
                    ]);

                $detalhes = 'Exclusao de receita reprovada';
                if (trim((string)$motivo) !== '') {
                    $detalhes .= ' | Motivo: '.trim((string)$motivo);
                }

                $this->auditar($userId, 'reprovar_exclusao_receita', 'receitas', $receitaId, $propertyId, $detalhes);
                return;
            }

            DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status_aprovacao' => 'reprovada',
                    'aprovado_por' => $userId,
                    'aprovado_em' => now(),
                    'motivo_reprovacao' => trim((string)$motivo) ?: null,
                ]);

            $detalhes = 'Receita reprovada';
            if (trim((string)$motivo) !== '') {
                $detalhes .= ' | Motivo: '.trim((string)$motivo);
            }

            $this->auditar($userId, 'reprovar_receita', 'receitas', $receitaId, $propertyId, $detalhes);
        });
    }

    public function receber(int $propertyId, int $receitaId, ?int $contaId, ?string $dataRecebimento, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $receitaId, $contaId, $dataRecebimento, $userId): void {
            $receita = DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$receita) {
                throw new RuntimeException('Receita nao encontrada para recebimento.');
            }

            if (($receita->status_aprovacao ?? '') !== 'aprovada') {
                throw new RuntimeException('Esta receita precisa ser aprovada antes do recebimento.');
            }

            if ($contaId && !DB::table('contas')->where('id', $contaId)->where('propriedade_id', $propertyId)->exists()) {
                $contaId = null;
            }

            DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status' => 'recebido',
                    'data_recebimento' => $dataRecebimento ?: now()->toDateString(),
                    'conta_id' => $contaId ?: $receita->conta_id,
                ]);

            $this->auditar($userId, 'receber_receita', 'receitas', $receitaId, $propertyId, 'Receita recebida');
        });
    }

    public function cancelar(int $propertyId, int $receitaId, ?int $userId): void
    {
        DB::transaction(function () use ($propertyId, $receitaId, $userId): void {
            $receita = DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->where('status', '!=', 'cancelado')
                ->lockForUpdate()
                ->first();

            if (!$receita) {
                throw new RuntimeException('Receita nao encontrada para exclusao.');
            }

            if (!$this->podeAprovar($propertyId, $userId)) {
                if ($this->temSolicitacaoExclusao($receita->observacoes ?? null)) {
                    throw new RuntimeException('A exclusao desta receita ja foi solicitada ao gestor.');
                }

                DB::table('receitas')
                    ->where('id', $receitaId)
                    ->where('propriedade_id', $propertyId)
                    ->update([
                        'status_aprovacao' => 'pendente',
                        'observacoes' => $this->adicionarSolicitacaoExclusao($receita->observacoes ?? null, $userId),
                    ]);

                $this->auditar($userId, 'solicitar_exclusao_receita', 'receitas', $receitaId, $propertyId, 'Exclusao de receita solicitada');
                return;
            }

            $observacoes = $this->limparSolicitacaoExclusao($receita->observacoes ?? null);
            $temSolicitacao = $this->temSolicitacaoExclusao($receita->observacoes ?? null);

            DB::table('receitas')
                ->where('id', $receitaId)
                ->where('propriedade_id', $propertyId)
                ->update([
                    'status' => 'cancelado',
                    'observacoes' => $observacoes ?: null,
                ]);

            $this->auditar(
                $userId,
                $temSolicitacao ? 'aprovar_exclusao_receita' : 'cancelar_receita',
                'receitas',
                $receitaId,
                $propertyId,
                $temSolicitacao ? 'Exclusao de receita aprovada' : 'Receita cancelada'
            );
        });
    }

    private function filtros(int $propertyId, Request $request): array
    {
        $safraId = $request->integer('safra_id') ?: null;
        if ($safraId && !DB::table('safras')->where('id', $safraId)->where('propriedade_id', $propertyId)->exists()) {
            $safraId = null;
        }

        return [
            'status' => in_array($request->query('status'), ['pendente', 'recebido'], true) ? (string)$request->query('status') : '',
            'aprovacao' => in_array($request->query('aprovacao'), ['pendente', 'aprovada', 'reprovada'], true) ? (string)$request->query('aprovacao') : '',
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('date_from')) ? (string)$request->query('date_from') : '',
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('date_to')) ? (string)$request->query('date_to') : '',
            'safra_id' => $safraId,
            'search' => trim((string)$request->query('search', '')),
        ];
    }

    private function compradores(int $propertyId): Collection
    {
        $this->garantirEstruturaCompradores();

        return DB::table('compradores')
            ->where('propriedade_id', $propertyId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'documento']);
    }

    private function importarCompradoresDeReceitas(int $propertyId): void
    {
        $this->garantirEstruturaCompradores();

        $nomes = DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('status', '!=', 'cancelado')
            ->whereNotNull('comprador')
            ->whereRaw("TRIM(comprador) <> ''")
            ->distinct()
            ->pluck('comprador');

        foreach ($nomes as $nome) {
            $nomeNormalizado = $this->normalizarComprador((string)$nome);
            if ($nomeNormalizado === '') {
                continue;
            }

            if (!DB::table('compradores')->where('propriedade_id', $propertyId)->where('nome', $nomeNormalizado)->exists()) {
                DB::table('compradores')->insert([
                    'propriedade_id' => $propertyId,
                    'nome' => $nomeNormalizado,
                    'ativo' => 1,
                ]);
            }
        }
    }

    private function garantirEstruturaCompradores(): void
    {
        if (Schema::hasTable('compradores')) {
            return;
        }

        Schema::create('compradores', function ($table) {
            $table->id();
            $table->unsignedBigInteger('propriedade_id')->index();
            $table->string('nome', 150);
            $table->string('documento', 30)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('criado_em')->useCurrent();
            $table->unique(['propriedade_id', 'nome'], 'uk_comprador_prop_nome');
        });
    }

    private function normalizarComprador(string $nome): string
    {
        $nome = trim((string)preg_replace('/\s+/', ' ', $nome));
        if ($nome === '') {
            return '';
        }

        $siglas = ['sa', 's/a', 'ltda', 'me', 'eireli', 'sicoob'];
        $partes = preg_split('/\s+/', strtolower($nome)) ?: [];

        $normalizadas = array_map(static function (string $parte) use ($siglas): string {
            $limpa = str_replace(['.', ','], '', $parte);
            if (in_array($limpa, $siglas, true)) {
                return strtoupper($parte);
            }

            return ucfirst($parte);
        }, $partes);

        return trim(implode(' ', $normalizadas));
    }

    private function rows(int $propertyId, array $filtros): Collection
    {
        $query = DB::table('receitas as r')
            ->leftJoin('safras as s', 's.id', '=', 'r.safra_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'r.categoria_id')
            ->leftJoin('categorias as sc', 'sc.id', '=', 'r.subcategoria_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'r.conta_id')
            ->leftJoin('produtores as p', 'p.id', '=', 'r.produtor_id')
            ->where('r.propriedade_id', $propertyId)
            ->where('r.status', '!=', 'cancelado');

        if ($filtros['status'] !== '') {
            $query->where('r.status', $filtros['status']);
        }

        if ($filtros['aprovacao'] !== '') {
            $query->where('r.status_aprovacao', $filtros['aprovacao']);
        }

        if ($filtros['date_from'] !== '') {
            $query->whereDate('r.data_venda', '>=', $filtros['date_from']);
        }

        if ($filtros['date_to'] !== '') {
            $query->whereDate('r.data_venda', '<=', $filtros['date_to']);
        }

        if ($filtros['safra_id']) {
            $query->where('r.safra_id', $filtros['safra_id']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('r.descricao', 'like', $term)
                    ->orWhere('r.comprador', 'like', $term)
                    ->orWhere('p.nome', 'like', $term);
            });
        }

        return $query
            ->orderByRaw("CASE WHEN r.status_aprovacao = 'pendente' THEN 0 ELSE 1 END")
            ->orderByDesc('r.data_venda')
            ->limit(220)
            ->get([
                'r.id',
                'r.descricao',
                'r.comprador',
                'r.quantidade',
                'r.unidade',
                'r.preco_unitario',
                'r.valor_total',
                'r.data_venda',
                'r.data_recebimento',
                'r.status',
                'r.status_aprovacao',
                'r.motivo_reprovacao',
                's.descricao as safra_nome',
                'c.nome as categoria_nome',
                'sc.nome as subcategoria_nome',
                'ct.nome as conta_nome',
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

        return (object)[
            'id' => (int)$row->id,
            'data_venda' => FarmFormat::date($row->data_venda),
            'descricao' => FarmFormat::value($row->descricao),
            'comprador' => FarmFormat::value($row->comprador),
            'categoria' => $categoria ?: '-',
            'produtor' => FarmFormat::value($row->produtor_nome),
            'quantidade' => $this->quantidade($row->quantidade, $row->unidade),
            'preco_unitario' => FarmFormat::money($row->preco_unitario),
            'valor_raw' => (float)$row->valor_total,
            'valor' => FarmFormat::money($row->valor_total),
            'safra' => FarmFormat::value($row->safra_nome),
            'conta' => FarmFormat::value($row->conta_nome),
            'recebimento' => FarmFormat::date($row->data_recebimento),
            'status_key' => (string)$row->status,
            'status' => $this->labelStatus((string)$row->status),
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

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'recebido' => 'Recebido',
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

    private function compradorIdPorNome(int $propertyId, string $nome): ?int
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }

        $this->importarCompradoresDeReceitas($propertyId);

        return DB::table('compradores')
            ->where('propriedade_id', $propertyId)
            ->where('nome', $this->normalizarComprador($nome))
            ->value('id');
    }

    private function compradorNome(int $propertyId, array $dados): ?string
    {
        $compradorId = (int)($dados['comprador_id'] ?? 0);
        if ($compradorId > 0) {
            $nome = DB::table('compradores')
                ->where('id', $compradorId)
                ->where('propriedade_id', $propertyId)
                ->where('ativo', 1)
                ->value('nome');

            if ($nome) {
                return (string)$nome;
            }
        }

        return trim((string)($dados['pessoa'] ?? '')) ?: null;
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
        return '[SOLICITACAO_EXCLUSAO_RECEITA]';
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
