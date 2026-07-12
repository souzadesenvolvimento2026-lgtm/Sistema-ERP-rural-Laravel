<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
