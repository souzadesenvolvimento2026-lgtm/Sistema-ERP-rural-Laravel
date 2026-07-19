<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProdutoService
{
    public function formOptions(): array
    {
        return [
            'categorias' => DB::table('categorias')
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome', 'tipo']),
        ];
    }

    public function pagina(int $propriedadeId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($propriedadeId, $filtros);

        return [
            'activeModule' => 'estoque-produtos',
            'title' => 'Estoque de Produtos',
            'subtitle' => 'Cadastro de produtos, insumos e dados fiscais usados nas entradas de nota fiscal.',
            'filtros' => $filtros,
            'rows' => $rows,
            ...$this->movimentacaoOptions($propriedadeId),
            'saidasRecentes' => $this->saidasRecentes($propriedadeId),
            'statusOptions' => [
                '' => 'Todos',
                'ativos' => 'Ativos',
                'inativos' => 'Inativos',
                'fiscal_pendente' => 'Fiscal pendente',
            ],
            'cards' => [
                ['label' => 'Produtos', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Ativos', 'value' => (string) $rows->where('ativo', true)->count(), 'tone' => 'success'],
                ['label' => 'Fiscal pendente', 'value' => (string) $rows->where('fiscal_completo', false)->count(), 'tone' => 'warning'],
                ['label' => 'Valor em estoque', 'value' => FarmFormat::money($rows->sum('valor_estoque_raw')), 'tone' => 'success'],
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        DB::table('produtos')->insert($this->dadosProduto($dados) + [
            'propriedade_id' => $propriedadeId,
            'ativo' => 1,
        ]);

        $produtoId = (int) DB::getPdo()->lastInsertId();
        $this->auditar($usuarioId, 'criar_produto_estoque', 'produtos', $produtoId, $propriedadeId, 'Produto criado no estoque de produtos');

        return $produtoId;
    }

    public function buscar(int $produtoId, int $propriedadeId): object
    {
        $produto = DB::table('produtos')
            ->where('id', $produtoId)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_if(! $produto, 404);

        return $produto;
    }

    public function atualizar(int $produtoId, array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        $this->buscar($produtoId, $propriedadeId);

        DB::table('produtos')
            ->where('id', $produtoId)
            ->where('propriedade_id', $propriedadeId)
            ->update($this->dadosProduto($dados));

        $this->auditar($usuarioId, 'editar_produto_estoque', 'produtos', $produtoId, $propriedadeId, 'Produto atualizado no estoque de produtos');
    }

    public function alternarStatus(int $produtoId, int $propriedadeId, ?int $usuarioId): bool
    {
        $produto = $this->buscar($produtoId, $propriedadeId);
        $ativo = (int) $produto->ativo === 1 ? 0 : 1;

        DB::table('produtos')
            ->where('id', $produtoId)
            ->where('propriedade_id', $propriedadeId)
            ->update(['ativo' => $ativo]);

        $this->auditar($usuarioId, 'alterar_status_produto_estoque', 'produtos', $produtoId, $propriedadeId, 'Status do produto alterado');

        return $ativo === 1;
    }

    public function registrarSaida(int $produtoId, int $propriedadeId, array $dados, ?int $usuarioId): int
    {
        $produto = $this->buscar($produtoId, $propriedadeId);
        $quantidade = $this->decimal($dados['quantidade'] ?? null);

        if ($quantidade <= 0) {
            throw ValidationException::withMessages([
                'quantidade' => 'Informe uma quantidade maior que zero para baixar o estoque.',
            ]);
        }

        $saldo = $this->saldoProduto($produtoId, $propriedadeId);
        if ($quantidade > ($saldo->quantidade + 0.00001)) {
            throw ValidationException::withMessages([
                'quantidade' => 'A quantidade informada é maior que o saldo disponível em estoque.',
            ]);
        }

        $destinoTipo = (string) $dados['destino_tipo'];
        $safraInformada = (int) ($dados['safra_id'] ?? 0) > 0;
        $safraId = $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId);
        $talhaoId = $this->idDaPropriedade('talhoes', $dados['talhao_id'] ?? null, $propriedadeId);
        $maquinaId = $this->idDaPropriedade('maquinas', $dados['maquina_id'] ?? null, $propriedadeId);
        $motivo = trim((string) ($dados['motivo'] ?? ''));
        $justificativaSemSafra = trim((string) ($dados['justificativa_sem_safra'] ?? ''));

        if ($safraInformada && $safraId === null) {
            throw ValidationException::withMessages([
                'safra_id' => 'Selecione uma safra válida desta propriedade.',
            ]);
        }

        if ($destinoTipo === 'safra' && $safraId === null) {
            throw ValidationException::withMessages([
                'safra_id' => 'Selecione a safra que receberá essa baixa de estoque.',
            ]);
        }

        if ($destinoTipo === 'patrimonio' && $maquinaId === null) {
            throw ValidationException::withMessages([
                'maquina_id' => 'Selecione o patrimônio que utilizou esse produto.',
            ]);
        }

        if ($destinoTipo === 'patrimonio' && $safraId === null && $justificativaSemSafra === '') {
            throw ValidationException::withMessages([
                'justificativa_sem_safra' => 'Informe a justificativa para baixar o estoque em patrimônio sem vincular uma safra.',
            ]);
        }

        if ($destinoTipo === 'ajuste' && $motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Informe o motivo da perda, devolução ou ajuste operacional.',
            ]);
        }

        $unidade = trim((string) ($produto->unidade_medida ?? '')) ?: 'un';
        $valorUnitario = $saldo->quantidade > 0 ? round($saldo->valor / $saldo->quantidade, 6) : 0.0;
        $valorTotal = round($quantidade * $valorUnitario, 2);
        $dataMovimento = (string) $dados['data_movimento'];
        $observacoes = $this->observacoesDaSaida(
            $destinoTipo,
            $motivo,
            $dados['observacoes'] ?? null,
            $justificativaSemSafra ?: null
        );

        return (int) DB::transaction(function () use (
            $produto,
            $produtoId,
            $propriedadeId,
            $usuarioId,
            $quantidade,
            $unidade,
            $valorUnitario,
            $valorTotal,
            $dataMovimento,
            $destinoTipo,
            $safraId,
            $talhaoId,
            $maquinaId,
            $motivo,
            $observacoes
        ) {
            $movimentoId = (int) DB::table('produto_estoque_movimentos')->insertGetId($this->filtrarColunas(
                'produto_estoque_movimentos',
                [
                    'propriedade_id' => $propriedadeId,
                    'produto_id' => $produtoId,
                    'origem_tipo' => 'baixa_estoque',
                    'origem_id' => $produtoId,
                    'tipo' => 'saida',
                    'destino_tipo' => $destinoTipo,
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                    'maquina_id' => $maquinaId,
                    'quantidade' => $quantidade,
                    'unidade' => $unidade,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $valorTotal,
                    'data_movimento' => $dataMovimento,
                    'motivo' => $motivo ?: null,
                    'observacoes' => $observacoes,
                    'usuario_id' => $usuarioId,
                ]
            ));

            if ($destinoTipo === 'patrimonio' && $maquinaId !== null) {
                $maquinaLancamentoId = $this->registrarLancamentoPatrimonio(
                    $produto,
                    $propriedadeId,
                    $movimentoId,
                    $maquinaId,
                    $safraId,
                    $talhaoId,
                    $quantidade,
                    $unidade,
                    $valorUnitario,
                    $valorTotal,
                    $dataMovimento,
                    $usuarioId,
                    $observacoes
                );

                if ($maquinaLancamentoId !== null && Schema::hasColumn('produto_estoque_movimentos', 'maquina_lancamento_id')) {
                    DB::table('produto_estoque_movimentos')
                        ->where('id', $movimentoId)
                        ->update(['maquina_lancamento_id' => $maquinaLancamentoId]);
                }
            }

            $detalhes = sprintf(
                'Baixa de %s %s do produto %s. Destino: %s.',
                FarmFormat::decimal($quantidade, 2),
                $unidade,
                $produto->descricao_generica,
                $this->destinoLabel($destinoTipo)
            );

            if ($destinoTipo === 'patrimonio' && $safraId === null) {
                $detalhes .= ' Sem safra vinculada, com justificativa registrada.';
            }

            $this->auditar($usuarioId, 'baixar_produto_estoque', 'produto_estoque_movimentos', $movimentoId, $propriedadeId, $detalhes);

            return $movimentoId;
        });
    }

    private function dadosProduto(array $dados): array
    {
        return [
            'codigo_interno' => trim($dados['codigo_interno'] ?? '') ?: null,
            'codigo_fornecedor' => trim($dados['codigo_fornecedor'] ?? '') ?: null,
            'descricao_original_nf' => trim($dados['descricao_original_nf'] ?? '') ?: null,
            'descricao_generica' => trim($dados['descricao_generica']),
            'descricao_detalhada' => trim($dados['descricao_detalhada'] ?? '') ?: null,
            'descricao_interna' => trim($dados['descricao_interna'] ?? '') ?: null,
            'unidade_medida' => trim($dados['unidade_medida'] ?? '') ?: 'un',
            'categoria_id' => ($dados['categoria_id'] ?? null) ?: null,
            'grupo' => trim($dados['grupo'] ?? '') ?: null,
            'subgrupo' => trim($dados['subgrupo'] ?? '') ?: null,
            'marca' => trim($dados['marca'] ?? '') ?: null,
            'ncm' => trim($dados['ncm'] ?? '') ?: null,
            'cest' => trim($dados['cest'] ?? '') ?: null,
            'cfop_entrada' => trim($dados['cfop_entrada'] ?? '') ?: null,
            'cst_icms' => trim($dados['cst_icms'] ?? '') ?: null,
            'csosn' => trim($dados['csosn'] ?? '') ?: null,
            'cst_pis' => trim($dados['cst_pis'] ?? '') ?: null,
            'cst_cofins' => trim($dados['cst_cofins'] ?? '') ?: null,
            'aliquota_icms' => $this->percentual($dados['aliquota_icms'] ?? null),
            'aliquota_pis' => $this->percentual($dados['aliquota_pis'] ?? null),
            'aliquota_cofins' => $this->percentual($dados['aliquota_cofins'] ?? null),
            'aliquota_ipi' => $this->percentual($dados['aliquota_ipi'] ?? null),
            'origem_mercadoria' => trim((string) ($dados['origem_mercadoria'] ?? '')) !== '' ? trim((string) $dados['origem_mercadoria']) : null,
            'tipo_item' => trim($dados['tipo_item'] ?? '') ?: null,
            'codigo_anp' => trim($dados['codigo_anp'] ?? '') ?: null,
            'informacoes_fiscais' => trim($dados['informacoes_fiscais'] ?? '') ?: null,
            'observacoes_fiscais' => trim($dados['observacoes_fiscais'] ?? '') ?: null,
        ];
    }

    private function percentual(mixed $valor): float
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }

        return max(0.0, (float) str_replace(',', '.', $valor));
    }

    private function filtros(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['', 'ativos', 'inativos', 'fiscal_pendente'], true)) {
            $status = '';
        }

        return [
            'status' => $status,
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function rows(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('produtos as p')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin(DB::raw("(
                SELECT produto_id,
                       SUM(CASE WHEN tipo = 'entrada' THEN quantidade WHEN tipo = 'saida' THEN -quantidade ELSE quantidade END) AS saldo_estoque,
                       SUM(CASE WHEN tipo = 'entrada' THEN valor_total WHEN tipo = 'saida' THEN -valor_total ELSE valor_total END) AS valor_estoque,
                       COUNT(*) AS movimentos_estoque
                FROM produto_estoque_movimentos
                WHERE propriedade_id = {$propriedadeId}
                GROUP BY produto_id
            ) as m"), 'm.produto_id', '=', 'p.id')
            ->leftJoin(DB::raw('(
                SELECT produto_id,
                       SUM(quantidade) AS quantidade_nf,
                       SUM(valor_total) AS valor_nf,
                       COUNT(*) AS itens_nf
                FROM nf_entrada_itens
                WHERE produto_id IS NOT NULL
                GROUP BY produto_id
            ) as nf'), 'nf.produto_id', '=', 'p.id')
            ->where('p.propriedade_id', $propriedadeId);

        if ($filtros['status'] === 'ativos') {
            $query->where('p.ativo', 1);
        } elseif ($filtros['status'] === 'inativos') {
            $query->where('p.ativo', 0);
        } elseif ($filtros['status'] === 'fiscal_pendente') {
            $query->where(function ($q) {
                $q->whereNull('p.ncm')
                    ->orWhere('p.ncm', '')
                    ->orWhereNull('p.cst_icms')
                    ->orWhere('p.cst_icms', '')
                    ->orWhereNull('p.cst_pis')
                    ->orWhere('p.cst_pis', '')
                    ->orWhereNull('p.cst_cofins')
                    ->orWhere('p.cst_cofins', '');
            });
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('p.descricao_generica', 'like', $term)
                    ->orWhere('p.codigo_interno', 'like', $term)
                    ->orWhere('p.codigo_fornecedor', 'like', $term)
                    ->orWhere('p.marca', 'like', $term)
                    ->orWhere('c.nome', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('p.ativo')
            ->orderBy('p.descricao_generica')
            ->get([
                'p.id',
                'p.descricao_generica',
                'p.codigo_interno',
                'p.codigo_fornecedor',
                'p.unidade_medida',
                'p.marca',
                'p.ativo',
                'p.ncm',
                'p.cst_icms',
                'p.cst_pis',
                'p.cst_cofins',
                'c.nome as categoria_nome',
                DB::raw('COALESCE(m.saldo_estoque, 0) as saldo_estoque'),
                DB::raw('COALESCE(m.valor_estoque, 0) as valor_estoque'),
                DB::raw('COALESCE(m.movimentos_estoque, 0) as movimentos_estoque'),
                DB::raw('COALESCE(nf.quantidade_nf, 0) as quantidade_nf'),
                DB::raw('COALESCE(nf.valor_nf, 0) as valor_nf'),
                DB::raw('COALESCE(nf.itens_nf, 0) as itens_nf'),
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function normalizar($row): object
    {
        $unidade = $this->unidadeLabel((string) ($row->unidade_medida ?: 'un'));
        $fiscalCompleto = $this->fiscalCompleto($row);

        return (object) [
            'id' => (int) $row->id,
            'descricao' => FarmFormat::value($row->descricao_generica),
            'codigo_interno' => FarmFormat::value($row->codigo_interno),
            'codigo_fornecedor' => FarmFormat::value($row->codigo_fornecedor),
            'categoria' => FarmFormat::value($row->categoria_nome),
            'unidade' => $unidade,
            'marca' => FarmFormat::value($row->marca),
            'ativo' => (int) $row->ativo === 1,
            'status' => (int) $row->ativo === 1 ? 'Ativo' : 'Inativo',
            'fiscal_completo' => $fiscalCompleto,
            'fiscal_status' => $fiscalCompleto ? 'Completo' : 'Pendente',
            'ncm' => FarmFormat::value($row->ncm),
            'saldo_estoque' => FarmFormat::decimal($row->saldo_estoque, 2).' '.$unidade,
            'saldo_estoque_raw' => (float) $row->saldo_estoque,
            'unidade_codigo' => (string) ($row->unidade_medida ?: 'un'),
            'valor_estoque' => FarmFormat::money($row->valor_estoque),
            'valor_estoque_raw' => (float) $row->valor_estoque,
            'movimentos_estoque' => (int) $row->movimentos_estoque,
            'quantidade_nf' => FarmFormat::decimal($row->quantidade_nf, 2).' '.$unidade,
            'valor_nf' => FarmFormat::money($row->valor_nf),
            'itens_nf' => (int) $row->itens_nf,
        ];
    }

    private function fiscalCompleto($row): bool
    {
        return trim((string) $row->ncm) !== ''
            && trim((string) $row->cst_icms) !== ''
            && trim((string) $row->cst_pis) !== ''
            && trim((string) $row->cst_cofins) !== '';
    }

    private function unidadeLabel(string $unidade): string
    {
        return [
            'kg' => 'Quilograma',
            'l' => 'Litro',
            'lt' => 'Litro',
            'un' => 'Unidade',
            'sc' => 'Saca',
        ][strtolower($unidade)] ?? $unidade;
    }

    private function movimentacaoOptions(int $propriedadeId): array
    {
        return [
            'safras' => $this->optionsSafras($propriedadeId),
            'talhoes' => $this->optionsTalhoes($propriedadeId),
            'patrimonios' => $this->optionsPatrimonios($propriedadeId),
        ];
    }

    private function optionsSafras(int $propriedadeId): Collection
    {
        if (! Schema::hasTable('safras')) {
            return collect();
        }

        return DB::table('safras')
            ->where('propriedade_id', $propriedadeId)
            ->orderByDesc('data_inicio')
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'status']);
    }

    private function optionsTalhoes(int $propriedadeId): Collection
    {
        if (! Schema::hasTable('talhoes')) {
            return collect();
        }

        $query = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId);

        if (Schema::hasColumn('talhoes', 'ativo')) {
            $query->where('ativo', 1);
        }

        $columns = ['id', 'nome'];

        if (Schema::hasColumn('talhoes', 'area_hectares')) {
            $columns[] = 'area_hectares';
        } elseif (Schema::hasColumn('talhoes', 'area_ha')) {
            $columns[] = DB::raw('area_ha as area_hectares');
        } elseif (Schema::hasColumn('talhoes', 'area')) {
            $columns[] = DB::raw('area as area_hectares');
        } else {
            $columns[] = DB::raw('0 as area_hectares');
        }

        return $query
            ->orderBy('nome')
            ->get($columns);
    }

    private function optionsPatrimonios(int $propriedadeId): Collection
    {
        if (! Schema::hasTable('maquinas')) {
            return collect();
        }

        $query = DB::table('maquinas')
            ->where('propriedade_id', $propriedadeId);

        if (Schema::hasColumn('maquinas', 'ativo')) {
            $query->where('ativo', 1);
        }

        return $query
            ->orderBy('nome')
            ->get(['id', 'nome', 'tipo']);
    }

    private function saidasRecentes(int $propriedadeId): Collection
    {
        if (! Schema::hasTable('produto_estoque_movimentos') || ! Schema::hasTable('produtos')) {
            return collect();
        }

        $hasDestinoTipo = Schema::hasColumn('produto_estoque_movimentos', 'destino_tipo');
        $hasSafra = Schema::hasColumn('produto_estoque_movimentos', 'safra_id') && Schema::hasTable('safras');
        $hasTalhao = Schema::hasColumn('produto_estoque_movimentos', 'talhao_id') && Schema::hasTable('talhoes');
        $hasMaquina = Schema::hasColumn('produto_estoque_movimentos', 'maquina_id') && Schema::hasTable('maquinas');
        $hasMotivo = Schema::hasColumn('produto_estoque_movimentos', 'motivo');

        $query = DB::table('produto_estoque_movimentos as m')
            ->join('produtos as p', 'p.id', '=', 'm.produto_id')
            ->where('m.propriedade_id', $propriedadeId)
            ->where('m.tipo', 'saida');

        if ($hasSafra) {
            $query->leftJoin('safras as s', 's.id', '=', 'm.safra_id');
        }

        if ($hasTalhao) {
            $query->leftJoin('talhoes as t', 't.id', '=', 'm.talhao_id');
        }

        if ($hasMaquina) {
            $query->leftJoin('maquinas as maq', 'maq.id', '=', 'm.maquina_id');
        }

        return $query
            ->orderByDesc('m.data_movimento')
            ->orderByDesc('m.id')
            ->limit(12)
            ->get([
                'm.id',
                'm.data_movimento',
                'm.quantidade',
                'm.unidade',
                'm.valor_total',
                'm.observacoes',
                'p.descricao_generica as produto_nome',
                DB::raw($hasDestinoTipo ? 'm.destino_tipo as destino_tipo' : 'NULL as destino_tipo'),
                DB::raw($hasMotivo ? 'm.motivo as motivo' : 'NULL as motivo'),
                DB::raw($hasSafra ? 's.descricao as safra_nome' : 'NULL as safra_nome'),
                DB::raw($hasTalhao ? 't.nome as talhao_nome' : 'NULL as talhao_nome'),
                DB::raw($hasMaquina ? 'maq.nome as maquina_nome' : 'NULL as maquina_nome'),
            ])
            ->map(function ($row) {
                $unidade = trim((string) ($row->unidade ?? '')) ?: 'un';

                return (object) [
                    'id' => (int) $row->id,
                    'data' => FarmFormat::date($row->data_movimento),
                    'produto' => FarmFormat::value($row->produto_nome),
                    'destino' => $this->destinoLabel($row->destino_tipo),
                    'safra' => FarmFormat::value($row->safra_nome),
                    'talhao' => FarmFormat::value($row->talhao_nome),
                    'patrimonio' => FarmFormat::value($row->maquina_nome),
                    'quantidade' => FarmFormat::decimal($row->quantidade, 2).' '.$unidade,
                    'valor' => FarmFormat::money($row->valor_total),
                    'observacoes' => FarmFormat::value($row->motivo ?: $row->observacoes),
                ];
            });
    }

    private function saldoProduto(int $produtoId, int $propriedadeId): object
    {
        $saldo = DB::table('produto_estoque_movimentos')
            ->where('produto_id', $produtoId)
            ->where('propriedade_id', $propriedadeId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN quantidade WHEN tipo = 'saida' THEN -quantidade ELSE quantidade END), 0) AS quantidade,
                COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor_total WHEN tipo = 'saida' THEN -valor_total ELSE valor_total END), 0) AS valor
            ")
            ->first();

        return (object) [
            'quantidade' => (float) ($saldo->quantidade ?? 0),
            'valor' => (float) ($saldo->valor ?? 0),
        ];
    }

    private function idDaPropriedade(string $tabela, mixed $id, int $propriedadeId): ?int
    {
        $id = (int) ($id ?? 0);
        if ($id <= 0 || ! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'propriedade_id')) {
            return null;
        }

        $existe = DB::table($tabela)
            ->where('id', $id)
            ->where('propriedade_id', $propriedadeId)
            ->exists();

        return $existe ? $id : null;
    }

    private function decimal(mixed $valor): float
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }

        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }

    private function observacoesDaSaida(
        string $destinoTipo,
        string $motivo,
        mixed $observacoes,
        ?string $justificativaSemSafra = null
    ): string
    {
        $partes = [
            'Baixa de estoque registrada pelo FarmFort.',
            'Destino: '.$this->destinoLabel($destinoTipo).'.',
            $motivo !== '' ? 'Motivo: '.$motivo.'.' : null,
            $justificativaSemSafra !== null && $justificativaSemSafra !== ''
                ? 'Justificativa sem safra: '.$justificativaSemSafra.'.'
                : null,
            trim((string) $observacoes) ?: null,
        ];

        return implode(' ', array_filter($partes));
    }

    private function destinoLabel(?string $destinoTipo): string
    {
        return [
            'safra' => 'Safra',
            'patrimonio' => 'Patrimônio',
            'ajuste' => 'Ajuste operacional',
        ][$destinoTipo ?: ''] ?? 'Saída de estoque';
    }

    private function registrarLancamentoPatrimonio(
        object $produto,
        int $propriedadeId,
        int $movimentoId,
        int $maquinaId,
        ?int $safraId,
        ?int $talhaoId,
        float $quantidade,
        string $unidade,
        float $valorUnitario,
        float $valorTotal,
        string $dataMovimento,
        ?int $usuarioId,
        string $observacoes
    ): ?int {
        if (! Schema::hasTable('maquina_lancamentos')) {
            return null;
        }

        $payload = $this->filtrarColunas('maquina_lancamentos', [
            'propriedade_id' => $propriedadeId,
            'maquina_id' => $maquinaId,
            'safra_id' => $safraId,
            'talhao_id' => $talhaoId,
            'tipo' => $this->tipoLancamentoPatrimonio($produto),
            'data_lancamento' => $dataMovimento,
            'descricao' => 'Uso de estoque: '.$produto->descricao_generica,
            'fornecedor' => null,
            'quantidade' => $quantidade,
            'unidade' => $unidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $valorTotal,
            'observacoes' => trim("ESTOQUE_MOVIMENTO #{$movimentoId}\n".$observacoes),
            'usuario_id' => $usuarioId,
        ]);

        if ($payload === []) {
            return null;
        }

        DB::table('maquina_lancamentos')->insert($payload);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function tipoLancamentoPatrimonio(object $produto): string
    {
        $texto = $this->semAcentos(mb_strtolower(implode(' ', [
            $produto->descricao_generica ?? '',
            $produto->descricao_interna ?? '',
            $produto->grupo ?? '',
            $produto->subgrupo ?? '',
        ]), 'UTF-8'));

        if (str_contains($texto, 'diesel') || str_contains($texto, 'combustivel')) {
            return 'abastecimento';
        }

        if (str_contains($texto, 'pneu') || str_contains($texto, 'peca')) {
            return 'pecas';
        }

        if (str_contains($texto, 'oleo') || str_contains($texto, 'lubrificante')) {
            return 'troca_oleo';
        }

        return 'outro';
    }

    private function semAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a',
            'à' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'õ' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ç' => 'c',
        ]);
    }

    private function filtrarColunas(string $tabela, array $dados): array
    {
        if (! Schema::hasTable($tabela)) {
            return [];
        }

        $colunas = array_flip(Schema::getColumnListing($tabela));

        return array_intersect_key($dados, $colunas);
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
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
