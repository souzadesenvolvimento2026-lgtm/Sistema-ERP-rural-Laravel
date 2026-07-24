<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class EntradaNfService
{
    public function formOptions(int $propertyId): array
    {
        return [
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'tipo']),
            'contas' => DB::table('contas')->where('propriedade_id', $propertyId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('id')->get(['id', 'descricao']),
        ];
    }

    public function pagina(int $propriedadeId, Request $request): array
    {
        $filtros = [
            'status' => trim((string) $request->query('status', '')),
            'fornecedor' => trim((string) $request->query('fornecedor', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $entradas = $this->entradas($propriedadeId, $filtros);

        return [
            'activeModule' => 'fiscal',
            'title' => 'Entrada de NF',
            'subtitle' => 'Cadastro manual de notas de entrada, itens e parcela financeira inicial.',
            'filtros' => $filtros,
            'entradas' => $entradas,
            'statusOptions' => [
                'rascunho' => 'Rascunho',
                'conferida' => 'Conferida',
                'aprovada' => 'Aprovada',
                'cancelada' => 'Cancelada',
            ],
            'cards' => [
                ['label' => 'Entradas', 'value' => (string) $entradas->count(), 'tone' => 'success'],
                ['label' => 'Rascunhos', 'value' => (string) $entradas->where('status_key', 'rascunho')->count(), 'tone' => 'warning'],
                ['label' => 'Com financeiro', 'value' => (string) $entradas->where('parcelas', '>', 0)->count(), 'tone' => 'success'],
                ['label' => 'Valor listado', 'value' => FarmFormat::money((float) $entradas->sum('valor_raw')), 'tone' => 'warning'],
            ],
        ];
    }

    public function criarManual(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        return DB::transaction(function () use ($dados, $propriedadeId, $usuarioId): int {
            $valorTotal = $this->money($dados['valor_total']);
            $valorProdutos = $this->money($dados['valor_produtos'] ?? $valorTotal);
            $valorFrete = $this->money($dados['valor_frete'] ?? 0);
            $valorDesconto = $this->money($dados['valor_desconto'] ?? 0);
            $valorImpostos = $this->money($dados['valor_impostos'] ?? 0);
            $valorFinanceiro = $this->money($dados['valor_financeiro_final'] ?? 0);
            if ($valorFinanceiro <= 0) {
                $valorFinanceiro = max(0, $valorTotal - $valorDesconto + $valorFrete);
            }

            DB::table('nf_entradas')->insert($this->filtrarColunas('nf_entradas', [
                'propriedade_id' => $propriedadeId,
                'numero' => trim($dados['numero']),
                'serie' => trim($dados['serie'] ?? '') ?: null,
                'chave_acesso' => trim($dados['chave_acesso'] ?? '') ?: null,
                'origem_lancamento' => 'manual',
                'data_emissao' => $dados['data_emissao'],
                'data_entrada' => $dados['data_entrada'],
                'fornecedor' => trim($dados['fornecedor']),
                'fornecedor_doc' => preg_replace('/\D+/', '', (string) ($dados['fornecedor_doc'] ?? '')) ?: null,
                'valor_total' => $valorTotal,
                'valor_produtos' => $valorProdutos,
                'valor_frete' => $valorFrete,
                'valor_desconto' => $valorDesconto,
                'valor_impostos' => $valorImpostos,
                'valor_financeiro_final' => $valorFinanceiro,
                'condicao_pagamento' => trim($dados['condicao_pagamento'] ?? '') ?: null,
                'forma_pagamento' => $dados['forma_pagamento'] ?: 'boleto',
                'conta_id' => ($dados['conta_id'] ?? null) ?: null,
                'categoria_id' => ($dados['categoria_id'] ?? null) ?: null,
                'safra_id' => ($dados['safra_id'] ?? null) ?: null,
                'centro_custo' => trim($dados['centro_custo'] ?? '') ?: null,
                'fazenda_unidade' => trim($dados['fazenda_unidade'] ?? '') ?: null,
                'observacoes_nota' => trim($dados['observacoes_nota'] ?? '') ?: null,
                'observacoes_financeiras' => trim($dados['observacoes_financeiras'] ?? '') ?: null,
                'status' => 'rascunho',
                'usuario_id' => $usuarioId,
                'classificar_patrimonio' => ! empty($dados['classificar_patrimonio']) ? 1 : 0,
                'patrimonio_nome' => trim($dados['patrimonio_nome'] ?? '') ?: null,
                'patrimonio_tipo' => $dados['patrimonio_tipo'] ?? null,
                'patrimonio_tipo_outro' => trim($dados['patrimonio_tipo_outro'] ?? '') ?: null,
                'patrimonio_controla_horimetro' => ! empty($dados['patrimonio_controla_horimetro']) ? 1 : 0,
                'patrimonio_controla_odometro' => ! empty($dados['patrimonio_controla_odometro']) ? 1 : 0,
            ]));

            $entradaId = (int) DB::getPdo()->lastInsertId();
            $this->sincronizarPatrimonio($entradaId, $dados, $propriedadeId);
            $this->criarItemInicial($entradaId, $dados);
            $this->criarParcelaInicial($entradaId, $dados, $valorFinanceiro);

            return $entradaId;
        });
    }

    public function detalhe(int $propriedadeId, int $entradaId): array
    {
        $entrada = DB::table('nf_entradas as nf')
            ->leftJoin('categorias as c', 'c.id', '=', 'nf.categoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'nf.safra_id')
            ->leftJoin('contas as ct', 'ct.id', '=', 'nf.conta_id')
            ->leftJoin('maquinas as m', 'm.id', '=', 'nf.patrimonio_id')
            ->where('nf.propriedade_id', $propriedadeId)
            ->where('nf.id', $entradaId)
            ->select([
                'nf.*',
                'c.nome as categoria_nome',
                's.descricao as safra_nome',
                'ct.nome as conta_nome',
                'm.nome as patrimonio_nome_rel',
            ])
            ->first();

        abort_unless($entrada, 404);

        $itens = DB::table('nf_entrada_itens as i')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'i.categoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'i.safra_id')
            ->where('i.nf_entrada_id', $entradaId)
            ->orderBy('i.id')
            ->get([
                'i.id',
                'i.descricao_nf',
                'i.descricao_generica',
                'i.quantidade',
                'i.unidade',
                'i.valor_unitario',
                'i.valor_total',
                'i.total_liquido',
                'i.fiscal_validado',
                'p.descricao_generica as produto_nome',
                'p.ncm',
                'p.cst_icms',
                'p.cst_pis',
                'p.cst_cofins',
                'c.nome as categoria_nome',
                's.descricao as safra_nome',
            ])
            ->map(fn ($item) => (object) [
                'descricao' => FarmFormat::value($item->descricao_generica ?: $item->descricao_nf ?: $item->produto_nome),
                'produto' => FarmFormat::value($item->produto_nome),
                'categoria' => FarmFormat::value($item->categoria_nome),
                'safra' => FarmFormat::value($item->safra_nome),
                'quantidade' => number_format((float) $item->quantidade, 4, ',', '.'),
                'unidade' => FarmFormat::value($item->unidade),
                'valor_unitario' => FarmFormat::money($item->valor_unitario),
                'valor_total' => FarmFormat::money($item->valor_total),
                'total_liquido' => FarmFormat::money($item->total_liquido),
                'fiscal_ok' => $this->itemFiscalOk($item),
            ]);

        $parcelas = DB::table('nf_entrada_parcelas as p')
            ->leftJoin('contas as ct', 'ct.id', '=', 'p.conta_id')
            ->where('p.nf_entrada_id', $entradaId)
            ->orderBy('p.parcela_numero')
            ->get([
                'p.id',
                'p.parcela_numero',
                'p.data_vencimento',
                'p.valor',
                'p.forma_pagamento',
                'p.status',
                'p.despesa_id',
                'ct.nome as conta_nome',
            ])
            ->map(fn ($parcela) => (object) [
                'numero' => (int) $parcela->parcela_numero,
                'vencimento' => FarmFormat::date($parcela->data_vencimento),
                'valor' => FarmFormat::money($parcela->valor),
                'forma' => $this->formaLabel((string) $parcela->forma_pagamento),
                'conta' => FarmFormat::value($parcela->conta_nome),
                'status_key' => (string) $parcela->status,
                'status' => $this->statusParcelaLabel((string) $parcela->status),
                'despesa_id' => $parcela->despesa_id ? (int) $parcela->despesa_id : null,
            ]);

        $somaItens = (float) DB::table('nf_entrada_itens')
            ->where('nf_entrada_id', $entradaId)
            ->sum('total_liquido');
        $somaParcelas = (float) DB::table('nf_entrada_parcelas')
            ->where('nf_entrada_id', $entradaId)
            ->where('status', '!=', 'cancelada')
            ->sum('valor');

        $valorFinal = (float) $entrada->valor_financeiro_final;

        return [
            'activeModule' => 'fiscal',
            'title' => 'Entrada NF '.$this->numeroDocumento($entrada->numero, $entrada->serie),
            'entrada' => (object) [
                'id' => (int) $entrada->id,
                'numero' => $this->numeroDocumento($entrada->numero, $entrada->serie),
                'numero_raw' => (string) $entrada->numero,
                'chave_acesso' => FarmFormat::value($entrada->chave_acesso),
                'fornecedor' => FarmFormat::value($entrada->fornecedor),
                'fornecedor_doc' => FarmFormat::value($entrada->fornecedor_doc),
                'data_emissao' => FarmFormat::date($entrada->data_emissao),
                'data_entrada' => FarmFormat::date($entrada->data_entrada),
                'categoria' => FarmFormat::value($entrada->categoria_nome),
                'safra' => FarmFormat::value($entrada->safra_nome),
                'conta' => FarmFormat::value($entrada->conta_nome),
                'patrimonio' => FarmFormat::value($entrada->patrimonio_nome_rel ?: $entrada->patrimonio_nome),
                'valor_total' => FarmFormat::money($entrada->valor_total),
                'valor_produtos' => FarmFormat::money($entrada->valor_produtos),
                'valor_financeiro' => FarmFormat::money($entrada->valor_financeiro_final),
                'status_key' => (string) $entrada->status,
                'status' => $this->statusLabel((string) $entrada->status),
                'financeiro_confirmado' => (bool) $entrada->financeiro_confirmado,
                'observacoes_nota' => FarmFormat::value($entrada->observacoes_nota),
                'observacoes_financeiras' => FarmFormat::value($entrada->observacoes_financeiras),
            ],
            'itens' => $itens,
            'parcelas' => $parcelas,
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'safras' => DB::table('safras')->where('propriedade_id', $propriedadeId)->orderByDesc('id')->get(['id', 'descricao']),
            'produtos' => DB::table('produtos')
                ->where('propriedade_id', $propriedadeId)
                ->where('ativo', 1)
                ->orderBy('descricao_generica')
                ->limit(250)
                ->get(['id', 'descricao_generica', 'unidade_medida', 'ncm']),
            'cards' => [
                ['label' => 'Valor financeiro', 'value' => FarmFormat::money($valorFinal), 'tone' => 'warning'],
                ['label' => 'Soma itens', 'value' => FarmFormat::money($somaItens), 'tone' => abs($somaItens - $valorFinal) <= 0.10 ? 'success' : 'danger'],
                ['label' => 'Soma parcelas', 'value' => FarmFormat::money($somaParcelas), 'tone' => abs($somaParcelas - $valorFinal) <= 0.10 ? 'success' : 'danger'],
                ['label' => 'Parcelas', 'value' => (string) $parcelas->count(), 'tone' => 'success'],
            ],
            'podeConcluir' => (string) $entrada->status !== 'concluida' && ! (bool) $entrada->financeiro_confirmado,
        ];
    }

    public function adicionarItem(int $propriedadeId, int $entradaId, array $dados): void
    {
        DB::transaction(function () use ($propriedadeId, $entradaId, $dados): void {
            $entrada = $this->entradaEditavel($propriedadeId, $entradaId);
            $produto = null;
            $produtoId = (int) ($dados['produto_id'] ?? 0);

            if ($produtoId > 0) {
                $produto = DB::table('produtos')
                    ->where('id', $produtoId)
                    ->where('propriedade_id', $propriedadeId)
                    ->first();
            }

            $descricaoNf = trim((string) ($dados['descricao_nf'] ?? ''));
            $descricaoGenerica = trim((string) ($dados['descricao_generica'] ?? ''));

            if (! $produto && $descricaoGenerica !== '') {
                $produtoId = $this->criarProdutoPorItem($propriedadeId, $dados, $descricaoNf ?: $descricaoGenerica, $descricaoGenerica);
                $produto = DB::table('produtos')->where('id', $produtoId)->first();
            }

            if ($produto) {
                $descricaoGenerica = $descricaoGenerica ?: (string) $produto->descricao_generica;
                $descricaoNf = $descricaoNf ?: (string) ($produto->descricao_original_nf ?: $produto->descricao_generica);
            }

            if ($descricaoNf === '' || $descricaoGenerica === '') {
                throw new RuntimeException('Informe a descricao do item ou selecione um produto cadastrado.');
            }

            $quantidade = $this->decimal($dados['quantidade'] ?? 1);
            $valorUnitario = $this->money($dados['valor_unitario'] ?? 0);
            $valorTotal = $this->money($dados['valor_total'] ?? 0);
            if ($valorTotal <= 0) {
                $valorTotal = round($quantidade * $valorUnitario, 2);
            }

            $desconto = $this->money($dados['desconto'] ?? 0);
            $freteRateado = $this->money($dados['frete_rateado'] ?? 0);
            $valorIpi = $this->money($dados['valor_ipi'] ?? 0);
            $totalLiquido = $this->money($dados['total_liquido'] ?? 0);
            if ($totalLiquido <= 0) {
                $totalLiquido = max(0, $valorTotal - $desconto + $freteRateado + $valorIpi);
            }

            $fiscal = (object) [
                'fiscal_validado' => 0,
                'ncm' => $dados['ncm'] ?? ($produto->ncm ?? null),
                'cst_icms' => $dados['cst_icms'] ?? ($produto->cst_icms ?? null),
                'cst_pis' => $dados['cst_pis'] ?? ($produto->cst_pis ?? null),
                'cst_cofins' => $dados['cst_cofins'] ?? ($produto->cst_cofins ?? null),
            ];

            DB::table('nf_entrada_itens')->insert($this->filtrarColunas('nf_entrada_itens', [
                'nf_entrada_id' => $entrada->id,
                'produto_id' => $produtoId ?: null,
                'descricao_nf' => $descricaoNf,
                'descricao_generica' => $descricaoGenerica,
                'descricao_detalhada' => trim((string) ($dados['descricao_detalhada'] ?? '')) ?: null,
                'descricao_interna' => trim((string) ($dados['descricao_interna'] ?? '')) ?: null,
                'descricao_uso' => $dados['descricao_uso'] ?? 'generica',
                'quantidade' => $quantidade,
                'unidade' => trim((string) ($dados['unidade'] ?? '')) ?: ($produto->unidade_medida ?? 'un'),
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotal,
                'desconto' => $desconto,
                'frete_rateado' => $freteRateado,
                'base_icms' => $this->money($dados['base_icms'] ?? 0),
                'valor_icms' => $this->money($dados['valor_icms'] ?? 0),
                'base_pis' => $this->money($dados['base_pis'] ?? 0),
                'valor_pis' => $this->money($dados['valor_pis'] ?? 0),
                'base_cofins' => $this->money($dados['base_cofins'] ?? 0),
                'valor_cofins' => $this->money($dados['valor_cofins'] ?? 0),
                'valor_ipi' => $valorIpi,
                'total_liquido' => $totalLiquido,
                'centro_custo' => trim((string) ($dados['centro_custo'] ?? '')) ?: null,
                'fazenda_unidade' => trim((string) ($dados['fazenda_unidade'] ?? '')) ?: null,
                'safra_id' => ($dados['safra_id'] ?? null) ?: $entrada->safra_id,
                'categoria_id' => ($dados['categoria_id'] ?? null) ?: $entrada->categoria_id,
                'fiscal_validado' => $this->itemFiscalOk($fiscal) ? 1 : 0,
            ]));
        });
    }

    public function gerarParcelas(int $propriedadeId, int $entradaId, int $quantidade, ?string $primeiroVencimento): void
    {
        DB::transaction(function () use ($propriedadeId, $entradaId, $quantidade, $primeiroVencimento): void {
            $entrada = $this->entradaEditavel($propriedadeId, $entradaId);
            $quantidade = max(1, $quantidade);
            $primeiroVencimento = $primeiroVencimento ?: now()->toDateString();
            $valorFinal = (float) $entrada->valor_financeiro_final;
            $valorParcela = round($valorFinal / $quantidade, 2);

            DB::table('nf_entrada_parcelas')
                ->where('nf_entrada_id', $entradaId)
                ->where('status', '!=', 'confirmada')
                ->delete();

            $saldo = $valorFinal;
            for ($parcela = 1; $parcela <= $quantidade; $parcela++) {
                $valor = $parcela === $quantidade ? max(0, $saldo) : $valorParcela;
                $saldo = round($saldo - $valor, 2);

                DB::table('nf_entrada_parcelas')->insert($this->filtrarColunas('nf_entrada_parcelas', [
                    'nf_entrada_id' => $entradaId,
                    'parcela_numero' => $parcela,
                    'data_vencimento' => Carbon::parse($primeiroVencimento)->addMonthsNoOverflow($parcela - 1)->toDateString(),
                    'valor' => $valor,
                    'forma_pagamento' => $entrada->forma_pagamento ?: 'boleto',
                    'conta_id' => $entrada->conta_id,
                    'observacoes' => 'Gerada pela entrada da NF',
                    'status' => 'pendente',
                ]));
            }
        });
    }

    public function concluir(int $propriedadeId, int $entradaId, ?int $usuarioId): void
    {
        DB::transaction(function () use ($propriedadeId, $entradaId, $usuarioId): void {
            $entrada = DB::table('nf_entradas')
                ->where('propriedade_id', $propriedadeId)
                ->where('id', $entradaId)
                ->lockForUpdate()
                ->first();

            if (! $entrada) {
                throw new RuntimeException('Entrada de NF nao encontrada.');
            }

            if ((string) $entrada->status === 'concluida' || (bool) $entrada->financeiro_confirmado) {
                throw new RuntimeException('Entrada de NF ja concluida.');
            }

            $itens = DB::table('nf_entrada_itens as i')
                ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
                ->where('i.nf_entrada_id', $entradaId)
                ->get([
                    'i.id',
                    'i.produto_id',
                    'i.quantidade',
                    'i.unidade',
                    'i.valor_unitario',
                    'i.valor_total',
                    'i.descricao_nf',
                    'i.descricao_generica',
                    'i.total_liquido',
                    'i.fiscal_validado',
                    'p.ncm',
                    'p.cst_icms',
                    'p.cst_pis',
                    'p.cst_cofins',
                ]);

            $parcelas = DB::table('nf_entrada_parcelas')
                ->where('nf_entrada_id', $entradaId)
                ->where('status', '!=', 'cancelada')
                ->orderBy('parcela_numero')
                ->lockForUpdate()
                ->get();

            $erros = $this->validarConclusao($entrada, $itens, $parcelas);
            if ($erros !== []) {
                throw new RuntimeException(implode(' ', $erros));
            }

            DB::table('nf_entradas')
                ->where('id', $entradaId)
                ->where('propriedade_id', $propriedadeId)
                ->update($this->filtrarColunas('nf_entradas', [
                    'status' => 'concluida',
                    'financeiro_confirmado' => 1,
                    'atualizado_em' => now(),
                ]));

            $totalParcelas = $parcelas->count();
            foreach ($parcelas as $parcela) {
                if (! empty($parcela->despesa_id)) {
                    continue;
                }

                DB::table('despesas')->insert($this->filtrarColunas('despesas', [
                    'propriedade_id' => $propriedadeId,
                    'safra_id' => $entrada->safra_id,
                    'categoria_id' => $entrada->categoria_id,
                    'conta_id' => $parcela->conta_id ?: $entrada->conta_id,
                    'descricao' => 'NF '.$entrada->numero.' - '.$entrada->fornecedor,
                    'fornecedor' => $entrada->fornecedor,
                    'valor_total' => (float) $parcela->valor,
                    'data_lancamento' => $entrada->data_entrada,
                    'data_vencimento' => $parcela->data_vencimento,
                    'status_pagamento' => 'pendente',
                    'status_aprovacao' => 'pendente',
                    'forma_pagamento' => $parcela->forma_pagamento,
                    'numero_parcelas' => $totalParcelas,
                    'parcela_atual' => (int) $parcela->parcela_numero,
                    'nota_fiscal' => $entrada->numero,
                    'observacoes' => 'Gerado pela entrada de NF #'.$entradaId,
                    'usuario_id' => $usuarioId,
                    'criado_em' => now(),
                ]));

                $despesaId = (int) DB::getPdo()->lastInsertId();
                DB::table('nf_entrada_parcelas')
                    ->where('id', $parcela->id)
                    ->where('nf_entrada_id', $entradaId)
                    ->update([
                        'status' => 'confirmada',
                        'despesa_id' => $despesaId,
                    ]);
            }

            $entradasEstoque = $this->registrarEntradasEstoque($entrada, $itens, $propriedadeId, $usuarioId);

            $this->auditar(
                $usuarioId,
                'concluir_entrada_nf',
                'nf_entradas',
                $entradaId,
                $propriedadeId,
                'Entrada de NF concluida, '.$totalParcelas.' titulo(s) financeiro(s) confirmado(s) e '
                    .$entradasEstoque.' entrada(s) de estoque registrada(s)'
            );
        });
    }

    private function entradas(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('nf_entradas as nf')
            ->leftJoin('categorias as c', 'c.id', '=', 'nf.categoria_id')
            ->leftJoin('safras as s', 's.id', '=', 'nf.safra_id')
            ->where('nf.propriedade_id', $propriedadeId);

        if ($filtros['status'] !== '') {
            $query->where('nf.status', $filtros['status']);
        }

        if ($filtros['fornecedor'] !== '') {
            $term = '%'.$filtros['fornecedor'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('nf.fornecedor', 'like', $term)
                    ->orWhere('nf.fornecedor_doc', 'like', $term)
                    ->orWhere('nf.numero', 'like', $term);
            });
        }

        if ($filtros['date_from'] !== '') {
            $query->whereDate('nf.data_entrada', '>=', $filtros['date_from']);
        }

        if ($filtros['date_to'] !== '') {
            $query->whereDate('nf.data_entrada', '<=', $filtros['date_to']);
        }

        return $query
            ->select([
                'nf.id',
                'nf.numero',
                'nf.serie',
                'nf.fornecedor',
                'nf.fornecedor_doc',
                'nf.data_entrada',
                'nf.valor_financeiro_final',
                'nf.status',
                'c.nome as categoria_nome',
                's.descricao as safra_nome',
                DB::raw('(SELECT COUNT(*) FROM nf_entrada_itens nei WHERE nei.nf_entrada_id = nf.id) as itens'),
                DB::raw('(SELECT COUNT(*) FROM nf_entrada_parcelas nep WHERE nep.nf_entrada_id = nf.id) as parcelas'),
            ])
            ->orderByDesc('nf.data_entrada')
            ->orderByDesc('nf.id')
            ->limit(180)
            ->get()
            ->map(fn ($entrada) => (object) [
                'id' => (int) $entrada->id,
                'numero' => $this->numeroDocumento($entrada->numero, $entrada->serie),
                'fornecedor' => FarmFormat::value($entrada->fornecedor),
                'fornecedor_doc' => FarmFormat::value($entrada->fornecedor_doc),
                'data_entrada' => FarmFormat::date($entrada->data_entrada),
                'valor_raw' => (float) $entrada->valor_financeiro_final,
                'valor' => FarmFormat::money($entrada->valor_financeiro_final),
                'status_key' => (string) $entrada->status,
                'status' => $this->statusLabel((string) $entrada->status),
                'categoria' => FarmFormat::value($entrada->categoria_nome),
                'safra' => FarmFormat::value($entrada->safra_nome),
                'itens' => (int) $entrada->itens,
                'parcelas' => (int) $entrada->parcelas,
            ]);
    }

    private function criarItemInicial(int $entradaId, array $dados): void
    {
        $descricao = trim($dados['item_descricao'] ?? '');
        if ($descricao === '') {
            return;
        }

        $quantidade = $this->decimal($dados['item_quantidade'] ?? 1);
        $unitario = $this->money($dados['item_valor_unitario'] ?? 0);
        $total = $this->money($dados['item_valor_total'] ?? 0);
        if ($total <= 0) {
            $total = round($quantidade * $unitario, 2);
        }

        DB::table('nf_entrada_itens')->insert([
            'nf_entrada_id' => $entradaId,
            'descricao_nf' => $descricao,
            'descricao_generica' => trim($dados['item_descricao_generica'] ?? '') ?: $descricao,
            'quantidade' => $quantidade,
            'unidade' => trim($dados['item_unidade'] ?? '') ?: 'un',
            'valor_unitario' => $unitario,
            'valor_total' => $total,
            'total_liquido' => $total,
            'categoria_id' => ($dados['categoria_id'] ?? null) ?: null,
            'safra_id' => ($dados['safra_id'] ?? null) ?: null,
            'fiscal_validado' => 0,
        ]);
    }

    private function criarParcelaInicial(int $entradaId, array $dados, float $valorFinanceiro): void
    {
        DB::table('nf_entrada_parcelas')->insert([
            'nf_entrada_id' => $entradaId,
            'parcela_numero' => 1,
            'data_vencimento' => ($dados['data_vencimento'] ?? null) ?: $dados['data_entrada'],
            'valor' => $valorFinanceiro,
            'forma_pagamento' => $dados['forma_pagamento'] ?: 'boleto',
            'conta_id' => ($dados['conta_id'] ?? null) ?: null,
            'observacoes' => trim($dados['observacoes_financeiras'] ?? '') ?: null,
            'status' => 'pendente',
        ]);
    }

    private function entradaEditavel(int $propriedadeId, int $entradaId): object
    {
        $entrada = DB::table('nf_entradas')
            ->where('id', $entradaId)
            ->where('propriedade_id', $propriedadeId)
            ->lockForUpdate()
            ->first();

        if (! $entrada) {
            throw new RuntimeException('Entrada de NF nao encontrada.');
        }

        if ((string) $entrada->status === 'concluida' || (bool) $entrada->financeiro_confirmado) {
            throw new RuntimeException('Entrada de NF ja concluida. Nao e possivel alterar itens ou parcelas.');
        }

        return $entrada;
    }

    private function criarProdutoPorItem(int $propriedadeId, array $dados, string $descricaoNf, string $descricaoGenerica): int
    {
        DB::table('produtos')->insert($this->filtrarColunas('produtos', [
            'propriedade_id' => $propriedadeId,
            'codigo_interno' => trim((string) ($dados['codigo_interno'] ?? '')) ?: null,
            'codigo_fornecedor' => trim((string) ($dados['codigo_fornecedor'] ?? '')) ?: null,
            'descricao_original_nf' => $descricaoNf,
            'descricao_generica' => $descricaoGenerica,
            'descricao_detalhada' => trim((string) ($dados['descricao_detalhada'] ?? '')) ?: null,
            'descricao_interna' => trim((string) ($dados['descricao_interna'] ?? '')) ?: null,
            'unidade_medida' => trim((string) ($dados['unidade'] ?? '')) ?: 'un',
            'categoria_id' => ($dados['categoria_id'] ?? null) ?: null,
            'grupo' => trim((string) ($dados['grupo'] ?? '')) ?: null,
            'subgrupo' => trim((string) ($dados['subgrupo'] ?? '')) ?: null,
            'marca' => trim((string) ($dados['marca'] ?? '')) ?: null,
            'ativo' => 1,
            'ncm' => trim((string) ($dados['ncm'] ?? '')) ?: null,
            'cest' => trim((string) ($dados['cest'] ?? '')) ?: null,
            'cfop_entrada' => trim((string) ($dados['cfop_entrada'] ?? '')) ?: null,
            'cst_icms' => trim((string) ($dados['cst_icms'] ?? '')) ?: null,
            'cst_pis' => trim((string) ($dados['cst_pis'] ?? '')) ?: null,
            'cst_cofins' => trim((string) ($dados['cst_cofins'] ?? '')) ?: null,
            'aliquota_icms' => $this->decimal($dados['aliquota_icms'] ?? 0),
            'aliquota_pis' => $this->decimal($dados['aliquota_pis'] ?? 0),
            'aliquota_cofins' => $this->decimal($dados['aliquota_cofins'] ?? 0),
            'aliquota_ipi' => $this->decimal($dados['aliquota_ipi'] ?? 0),
            'criado_em' => now(),
        ]));

        return (int) DB::getPdo()->lastInsertId();
    }

    private function sincronizarPatrimonio(int $entradaId, array $dados, int $propriedadeId): void
    {
        if (empty($dados['classificar_patrimonio'])) {
            return;
        }

        $nome = trim($dados['patrimonio_nome'] ?? '');
        if ($nome === '') {
            $nome = 'NF '.trim($dados['numero']).' - '.trim($dados['fornecedor']);
        }

        $tipo = $dados['patrimonio_tipo'] ?? 'outro';
        if (! in_array($tipo, ['trator', 'colheitadeira', 'plantadeira', 'pulverizador', 'caminhao', 'implemento', 'outro'], true)) {
            $tipo = 'outro';
        }

        $valorFinanceiro = $this->money($dados['valor_financeiro_final'] ?? 0);
        if ($valorFinanceiro <= 0) {
            $valorFinanceiro = max(0, $this->money($dados['valor_total'] ?? 0) - $this->money($dados['valor_desconto'] ?? 0) + $this->money($dados['valor_frete'] ?? 0));
        }

        DB::table('maquinas')->insert($this->filtrarColunas('maquinas', [
            'propriedade_id' => $propriedadeId,
            'nome' => $nome,
            'tipo' => $tipo,
            'tipo_outro' => $tipo === 'outro' ? trim($dados['patrimonio_tipo_outro'] ?? '') ?: null : null,
            'descricao_patrimonio' => 'Patrimonio criado a partir da entrada fiscal #'.$entradaId.'.',
            'valor_aquisicao' => $valorFinanceiro,
            'data_aquisicao' => $dados['data_entrada'],
            'fornecedor' => trim($dados['fornecedor']),
            'fornecedor_doc' => preg_replace('/\D+/', '', (string) ($dados['fornecedor_doc'] ?? '')) ?: null,
            'nota_fiscal_numero' => trim($dados['numero']),
            'nota_fiscal_serie' => trim($dados['serie'] ?? '') ?: null,
            'nota_fiscal_chave' => preg_replace('/\D+/', '', (string) ($dados['chave_acesso'] ?? '')) ?: null,
            'nf_entrada_id' => $entradaId,
            'controla_horimetro' => ! empty($dados['patrimonio_controla_horimetro']) ? 1 : 0,
            'controla_odometro' => ! empty($dados['patrimonio_controla_odometro']) ? 1 : 0,
            'ativo' => 1,
        ]));
        $patrimonioId = (int) DB::getPdo()->lastInsertId();

        if (Schema::hasColumn('nf_entradas', 'patrimonio_id')) {
            DB::table('nf_entradas')
                ->where('id', $entradaId)
                ->where('propriedade_id', $propriedadeId)
                ->update(['patrimonio_id' => $patrimonioId]);
        }
    }

    private function money($value): float
    {
        $value = trim((string) $value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float) $value);
    }

    private function decimal($value): float
    {
        $value = str_replace(',', '.', trim((string) $value));

        return max(0.0, (float) $value);
    }

    private function numeroDocumento(?string $numero, ?string $serie): string
    {
        $numero = FarmFormat::value($numero);
        if (! $serie) {
            return $numero;
        }

        return $numero.' / Serie '.$serie;
    }

    private function statusLabel(string $status): string
    {
        return FarmFormat::statusLabel($status);
    }

    private function validarConclusao(object $entrada, Collection $itens, Collection $parcelas): array
    {
        $erros = [];

        if ($itens->isEmpty()) {
            $erros[] = 'Inclua ao menos um produto/item na NF.';
        }

        foreach ($itens as $item) {
            if (! $this->itemFiscalOk($item)) {
                $descricao = $item->descricao_generica ?: $item->descricao_nf;
                $erros[] = 'Produto "'.$descricao.'" sem NCM/CST ICMS/PIS/COFINS.';
            }
        }

        $valorFinal = (float) $entrada->valor_financeiro_final;
        $somaItens = (float) $itens->sum(fn ($item) => (float) $item->total_liquido);
        $somaParcelas = (float) $parcelas->sum(fn ($parcela) => (float) $parcela->valor);

        if (abs($somaItens - $valorFinal) > 0.10) {
            $erros[] = 'A soma liquida dos itens ('.FarmFormat::money($somaItens).') precisa bater com o valor financeiro final ('.FarmFormat::money($valorFinal).').';
        }

        if ($parcelas->isEmpty() || abs($somaParcelas - $valorFinal) > 0.10) {
            $erros[] = 'As parcelas financeiras precisam bater com o valor financeiro final da nota.';
        }

        if (empty($entrada->categoria_id)) {
            $erros[] = 'Informe a categoria financeira da capa.';
        }

        return $erros;
    }

    private function itemFiscalOk(object $item): bool
    {
        if (! empty($item->fiscal_validado)) {
            return true;
        }

        return trim((string) ($item->ncm ?? '')) !== ''
            && trim((string) ($item->cst_icms ?? '')) !== ''
            && trim((string) ($item->cst_pis ?? '')) !== ''
            && trim((string) ($item->cst_cofins ?? '')) !== '';
    }

    private function registrarEntradasEstoque(
        object $entrada,
        Collection $itens,
        int $propriedadeId,
        ?int $usuarioId
    ): int {
        if (! Schema::hasTable('produto_estoque_movimentos')) {
            return 0;
        }

        $dataMovimento = $entrada->data_entrada ?: ($entrada->data_emissao ?: now()->toDateString());
        $numeroDocumento = $this->numeroDocumento($entrada->numero ?? null, $entrada->serie ?? null);
        $entradasRegistradas = 0;

        foreach ($itens as $item) {
            $produtoId = (int) ($item->produto_id ?? 0);
            $quantidade = (float) ($item->quantidade ?? 0);

            if ($produtoId <= 0 || $quantidade <= 0) {
                continue;
            }

            if ($this->estoqueJaRegistradoParaNfItem((int) ($item->id ?? 0), $produtoId)) {
                continue;
            }

            $valorUnitario = (float) ($item->valor_unitario ?? 0);
            $valorTotal = (float) ($item->valor_total ?? $item->total_liquido ?? 0);

            if ($valorTotal <= 0 && $valorUnitario > 0) {
                $valorTotal = round($valorUnitario * $quantidade, 2);
            }

            DB::table('produto_estoque_movimentos')->insert($this->filtrarColunas('produto_estoque_movimentos', [
                'propriedade_id' => $propriedadeId,
                'produto_id' => $produtoId,
                'origem_tipo' => 'entrada_nf',
                'origem_id' => (int) $entrada->id,
                'nf_entrada_id' => (int) $entrada->id,
                'nf_entrada_item_id' => (int) ($item->id ?? 0),
                'tipo' => 'entrada',
                'quantidade' => $quantidade,
                'unidade' => trim((string) ($item->unidade ?? '')) ?: 'un',
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotal,
                'custo_unitario' => $valorUnitario,
                'custo_total' => $valorTotal,
                'data_movimento' => $dataMovimento,
                'observacoes' => 'Entrada automatica pela conclusao da NF '.$numeroDocumento.'.',
                'usuario_id' => $usuarioId,
                'criado_em' => now(),
                'atualizado_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $entradasRegistradas++;
        }

        return $entradasRegistradas;
    }

    private function estoqueJaRegistradoParaNfItem(int $itemId, int $produtoId): bool
    {
        if (
            $itemId <= 0
            || ! Schema::hasTable('produto_estoque_movimentos')
            || ! Schema::hasColumn('produto_estoque_movimentos', 'nf_entrada_item_id')
        ) {
            return false;
        }

        return DB::table('produto_estoque_movimentos')
            ->where('produto_id', $produtoId)
            ->where('nf_entrada_item_id', $itemId)
            ->exists();
    }

    private function statusParcelaLabel(string $status): string
    {
        return match ($status) {
            'confirmada' => 'Confirmada',
            'cancelada' => 'Cancelada',
            default => 'Pendente',
        };
    }

    private function formaLabel(string $forma): string
    {
        return match ($forma) {
            'dinheiro' => 'Dinheiro',
            'pix' => 'Pix',
            'boleto' => 'Boleto',
            'cheque' => 'Cheque',
            'transferencia' => 'Transferencia',
            'cartao' => 'Cartao',
            default => FarmFormat::value($forma),
        };
    }

    private function filtrarColunas(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, (string) $column))
            ->all();
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
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
