<?php

namespace App\Services;

use App\Support\FarmContext;
use App\Support\FarmFormat;
use Illuminate\Support\Facades\DB;

class PlanejamentoFinanceiroService
{
    public function indexData(): array
    {
        $propertyId = $this->propertyId();
        $rows = $this->projecoes($propertyId);
        $receitas = (float)$rows->where('tipo_lancamento_raw', 'receita')->sum('valor_projetado_raw');
        $despesas = (float)$rows->where('tipo_lancamento_raw', 'despesa')->sum('valor_projetado_raw');

        return [
            'activeModule' => 'orcamento',
            'cards' => [
                ['label' => 'Receitas projetadas', 'value' => FarmFormat::money($receitas), 'tone' => 'success'],
                ['label' => 'Despesas projetadas', 'value' => FarmFormat::money($despesas), 'tone' => 'danger'],
                ['label' => 'Resultado projetado', 'value' => FarmFormat::money($receitas - $despesas), 'tone' => $receitas >= $despesas ? 'success' : 'danger'],
                ['label' => 'Projeções', 'value' => (string)$rows->count(), 'tone' => 'success'],
            ],
            'rows' => $rows,
            'safras' => DB::table('safras')
                ->where('propriedade_id', $propertyId)
                ->orderByDesc('data_inicio')
                ->orderByDesc('id')
                ->get(['id', 'descricao', 'area_plantada', 'producao_estimada', 'preco_estimado']),
            'culturas' => DB::table('culturas')->orderBy('nome')->get(['id', 'nome', 'unidade_producao']),
            'talhoes' => DB::table('talhoes')
                ->where('propriedade_id', $propertyId)
                ->where('ativo', 1)
                ->orderBy('nome')
                ->get(['id', 'nome', 'area']),
            'tiposAtividade' => $this->tiposAtividade(),
            'atividadesPlanejadas' => DB::table('atividades_campo as a')
                ->leftJoin('safras as s', 's.id', '=', 'a.safra_id')
                ->leftJoin('talhoes as t', 't.id', '=', 'a.talhao_id')
                ->where('a.propriedade_id', $propertyId)
                ->where('a.status', 'planejada')
                ->orderByDesc('a.data_inicio')
                ->orderByDesc('a.id')
                ->limit(25)
                ->get([
                    'a.id',
                    'a.tipo',
                    'a.descricao',
                    'a.data_inicio',
                    'a.data_fim',
                    'a.area_executada',
                    'a.responsavel',
                    'a.custo_estimado',
                    's.descricao as safra',
                    't.nome as talhao',
                ]),
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'tipo']),
        ];
    }

    public function planejamentoSafraData(?string $filtroSafra): array
    {
        $propertyId = $this->propertyId();
        $safras = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->orderByDesc('data_inicio')
            ->get(['id', 'descricao', 'data_inicio', 'status']);

        $safraId = $this->safraFiltro($safras, $filtroSafra);
        $totalProjetado = $this->totalProjetado($propertyId, $safraId);
        $totalRealizado = $this->totalRealizado($propertyId, $safraId);
        $percentual = $totalProjetado > 0 ? ($totalRealizado / $totalProjetado) * 100 : 0;

        return [
            'activeModule' => 'financeiro',
            'title' => 'Resultado da Safra x Projetado',
            'subtitle' => 'Acompanhamento do realizado financeiro contra o planejamento cadastrado.',
            'safras' => $safras,
            'safraId' => $safraId,
            'filtroSafra' => $safraId ? (string)$safraId : 'global',
            'cards' => [
                ['label' => 'Resultado da Safra', 'value' => FarmFormat::money($totalRealizado), 'tone' => 'warning'],
                ['label' => 'Projetado', 'value' => FarmFormat::money($totalProjetado), 'tone' => 'success'],
                ['label' => 'Atingido', 'value' => number_format($percentual, 2, ',', '.').'%', 'tone' => $percentual >= 100 ? 'success' : 'warning'],
                ['label' => 'Diferenca', 'value' => FarmFormat::money($totalRealizado - $totalProjetado), 'tone' => $totalRealizado >= $totalProjetado ? 'success' : 'danger'],
            ],
            'mensal' => $this->planejamentoMensal($propertyId, $safraId),
            'categorias' => $this->planejamentoCategorias($propertyId, $safraId),
            'projecoes' => $this->projecoes($propertyId, $safraId, 80),
        ];
    }

    public function formData(?int $id = null): array
    {
        $propertyId = $this->propertyId();

        return [
            'activeModule' => 'orcamento',
            'projecao' => $id ? $this->find($id) : null,
            'safras' => DB::table('safras')->where('propriedade_id', $propertyId)->orderByDesc('id')->get(['id', 'descricao']),
            'culturas' => DB::table('culturas')->orderBy('nome')->get(['id', 'nome']),
            'categorias' => DB::table('categorias')->where('ativo', 1)->orderBy('nome')->get(['id', 'nome', 'tipo']),
        ];
    }

    public function criar(array $dados, ?int $usuarioId): int
    {
        DB::table('financeiro_projecoes')->insert($this->payload($dados, $usuarioId));
        return (int)DB::getPdo()->lastInsertId();
    }

    public function atualizar(int $id, array $dados, ?int $usuarioId): void
    {
        $this->find($id);
        DB::table('financeiro_projecoes')
            ->where('id', $id)
            ->where('propriedade_id', $this->propertyId())
            ->update($this->payload($dados, $usuarioId));
    }

    public function excluir(int $id): void
    {
        $this->find($id);
        DB::table('financeiro_projecoes')
            ->where('id', $id)
            ->where('propriedade_id', $this->propertyId())
            ->delete();
    }

    public function atualizarPlanejamentoEmLote(array $dados, ?int $usuarioId): int
    {
        $propertyId = $this->propertyId();
        $ids = $dados['projecao_id'] ?? [];
        $categorias = $dados['categoria_id'] ?? [];
        $subcategorias = $dados['subcategoria_id'] ?? [];
        $meses = $dados['mes_referencia'] ?? [];
        $safras = $dados['safra_id'] ?? [];
        $culturas = $dados['cultura_id'] ?? [];
        $tiposLancamento = $dados['tipo_lancamento'] ?? [];
        $tiposSafra = $dados['tipo_safra'] ?? [];
        $anosSafra = $dados['ano_safra'] ?? [];
        $quantidades = $dados['quantidade'] ?? [];
        $unidades = $dados['unidade'] ?? [];
        $unitarios = $dados['valor_unitario'] ?? [];
        $totais = $dados['valor_projetado'] ?? [];
        $observacoes = $dados['observacoes'] ?? [];
        $totalAtualizado = 0;

        foreach ($ids as $idx => $id) {
            $id = (int)$id;
            $categoriaId = (int)($categorias[$idx] ?? 0);
            if ($categoriaId <= 0 || !DB::table('categorias')->where('id', $categoriaId)->where('ativo', 1)->exists()) {
                continue;
            }

            $mesReferencia = trim((string)($meses[$idx] ?? ''));
            if (preg_match('/^\d{4}-\d{2}$/', $mesReferencia)) {
                $mesReferencia .= '-01';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mesReferencia)) {
                $mesReferencia = date('Y-m-01');
            }

            $valorTotal = $this->money($totais[$idx] ?? 0);
            if ($valorTotal <= 0) {
                continue;
            }

            $payload = [
                'categoria_id' => $categoriaId,
                'subcategoria_id' => $this->subcategoriaId($subcategorias[$idx] ?? null, $categoriaId),
                'mes_referencia' => $mesReferencia,
                'quantidade' => $this->decimal($quantidades[$idx] ?? 0),
                'unidade' => trim((string)($unidades[$idx] ?? '')) ?: null,
                'valor_unitario' => $this->money($unitarios[$idx] ?? 0),
                'valor_projetado' => $valorTotal,
                'observacoes' => trim((string)($observacoes[$idx] ?? '')) ?: null,
                'usuario_id' => $usuarioId,
            ];

            if ($id > 0) {
                $updated = DB::table('financeiro_projecoes')
                    ->where('id', $id)
                    ->where('propriedade_id', $propertyId)
                    ->update($payload);

                $totalAtualizado += $updated;
                continue;
            }

            $anoSafra = trim((string)($anosSafra[$idx] ?? ''));
            if ($anoSafra === '') {
                continue;
            }

            $culturaId = (int)($culturas[$idx] ?? 0);
            DB::table('financeiro_projecoes')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $this->idDaPropriedade('safras', $safras[$idx] ?? null),
                'cultura_id' => $culturaId > 0 && DB::table('culturas')->where('id', $culturaId)->exists() ? $culturaId : null,
                'tipo_lancamento' => in_array(($tiposLancamento[$idx] ?? ''), ['receita', 'despesa'], true) ? $tiposLancamento[$idx] : 'despesa',
                'tipo_safra' => in_array(($tiposSafra[$idx] ?? ''), ['principal', 'safrinha'], true) ? $tiposSafra[$idx] : 'principal',
                'ano_safra' => $anoSafra,
                ...$payload,
            ]);

            $totalAtualizado++;
        }

        return $totalAtualizado;
    }

    public function criarRecorrente(array $dados, ?int $usuarioId): int
    {
        $inicio = \DateTimeImmutable::createFromFormat('Y-m-d', $dados['mes_inicial'].'-01');
        $fim = \DateTimeImmutable::createFromFormat('Y-m-d', $dados['mes_final'].'-01');
        abort_if(!$inicio || !$fim || $fim < $inicio, 422, 'O mes final deve ser igual ou posterior ao mes inicial.');

        $valor = $this->money($dados['valor_projetado'] ?? 0);
        abort_if($valor <= 0, 422, 'Informe um valor maior que zero para a recorrencia.');

        $grupo = 'REC-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
        $total = 0;
        $cursor = $inicio;
        $propertyId = $this->propertyId();
        $safraId = $this->idDaPropriedade('safras', $dados['safra_id'] ?? null);

        while ($cursor <= $fim && $total < 36) {
            DB::table('financeiro_projecoes')->insert([
                'propriedade_id' => $propertyId,
                'safra_id' => $safraId,
                'cultura_id' => ($dados['cultura_id'] ?? null) ?: null,
                'tipo_lancamento' => 'despesa',
                'tipo_safra' => $dados['tipo_safra'] ?: 'principal',
                'ano_safra' => trim($dados['ano_safra']),
                'mes_referencia' => $cursor->format('Y-m-d'),
                'categoria_id' => (int)$dados['categoria_id'],
                'subcategoria_id' => ($dados['subcategoria_id'] ?? null) ?: null,
                'quantidade' => 0,
                'unidade' => null,
                'valor_unitario' => 0,
                'valor_projetado' => $valor,
                'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
                'recorrencia_grupo' => $grupo,
                'usuario_id' => $usuarioId,
            ]);
            $total++;
            $cursor = $cursor->modify('+1 month');
        }

        return $total;
    }

    public function atualizarBaseSafra(array $dados): void
    {
        $safraId = $this->idDaPropriedade('safras', $dados['safra_id'] ?? null);
        abort_if(!$safraId, 422, 'Selecione uma safra valida para atualizar a base do planejamento.');

        DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $this->propertyId())
            ->update([
                'area_plantada' => $this->nullableDecimal($dados['area_plantada'] ?? null),
                'producao_estimada' => $this->nullableDecimal($dados['producao_estimada'] ?? null),
                'preco_estimado' => $this->nullableMoney($dados['preco_estimado'] ?? null),
            ]);
    }

    public function criarCategoriaPlanejamento(array $dados): int
    {
        $nome = preg_replace('/\s+/', ' ', trim((string)($dados['nome'] ?? '')));
        abort_if(strlen($nome) < 2, 422, 'Informe o nome da categoria.');

        $categoriaId = (int)(DB::table('categorias')
            ->whereNull('categoria_pai_id')
            ->whereRaw('LOWER(nome) = LOWER(?)', [$nome])
            ->value('id') ?: 0);

        if ($categoriaId > 0) {
            DB::table('categorias')->where('id', $categoriaId)->update(['ativo' => 1]);

            return $categoriaId;
        }

        DB::table('categorias')->insert([
            'categoria_pai_id' => null,
            'nome' => $nome,
            'tipo' => 'outros',
            'cor' => '#2fc89b',
            'icone' => 'bi-tag',
            'ativo' => 1,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function criarCulturaPlanejamento(array $dados): int
    {
        $nome = preg_replace('/\s+/', ' ', trim((string)($dados['nome'] ?? '')));
        abort_if(strlen($nome) < 2, 422, 'Informe o nome da cultura.');

        $culturaId = (int)(DB::table('culturas')
            ->whereRaw('LOWER(nome) = LOWER(?)', [$nome])
            ->value('id') ?: 0);

        if ($culturaId > 0) {
            return $culturaId;
        }

        DB::table('culturas')->insert([
            'nome' => $nome,
            'unidade_producao' => 'sc',
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function criarSafraRetroativa(array $dados): int
    {
        $propertyId = $this->propertyId();
        $descricao = preg_replace('/\s+/', ' ', trim((string)($dados['descricao'] ?? '')));
        abort_if($descricao === '', 422, 'Informe o nome da safra retroativa.');

        $inicio = \DateTimeImmutable::createFromFormat('Y-m-d', $dados['data_inicio']);
        abort_if(!$inicio, 422, 'Informe a data de inicio da safra retroativa.');

        $fim = null;
        if (!empty($dados['data_fim'])) {
            $fim = \DateTimeImmutable::createFromFormat('Y-m-d', $dados['data_fim']);
            abort_if(!$fim || $fim < $inicio, 422, 'A data final nao pode ser anterior ao inicio da safra.');
        }

        $culturaId = !empty($dados['cultura_id']) && DB::table('culturas')->where('id', (int)$dados['cultura_id'])->exists()
            ? (int)$dados['cultura_id']
            : null;

        return DB::transaction(function () use ($propertyId, $dados, $descricao, $inicio, $fim, $culturaId) {
            DB::table('safras')->insert([
                'propriedade_id' => $propertyId,
                'cultura_id' => $culturaId,
                'safra_referencia' => 'primeira',
                'descricao' => $descricao,
                'data_inicio' => $inicio->format('Y-m-d'),
                'data_fim' => $fim?->format('Y-m-d'),
                'area_plantada' => $this->nullableDecimal($dados['area_plantada'] ?? null),
                'producao_estimada' => $this->nullableDecimal($dados['producao_estimada'] ?? null),
                'producao_realizada' => $this->nullableDecimal($dados['producao_realizada'] ?? null),
                'preco_estimado' => $this->nullableMoney($dados['preco_estimado'] ?? null),
                'status' => 'encerrada',
                'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: 'Safra retroativa cadastrada pelo planejamento da safra.',
            ]);

            $safraId = (int)DB::getPdo()->lastInsertId();
            $anoInicio = $this->anoAgricolaInicio($inicio);

            DB::table('anos_agricolas')->updateOrInsert(
                [
                    'propriedade_id' => $propertyId,
                    'ano_inicio' => $anoInicio,
                ],
                [
                    'descricao' => $this->anoAgricolaLabel($anoInicio),
                    'data_inicio' => sprintf('%04d-07-01', $anoInicio),
                    'data_fim' => sprintf('%04d-06-30', $anoInicio + 1),
                    'observacoes' => 'Ano criado automaticamente por safra retroativa.',
                ]
            );

            return $safraId;
        });
    }

    public function salvarAnoAgricola(array $dados): int
    {
        $anoInicio = (int)($dados['ano_inicio'] ?? 0);
        abort_if($anoInicio < 2000 || $anoInicio > 2100, 422, 'Informe um ano agricola valido.');

        DB::table('anos_agricolas')->updateOrInsert(
            [
                'propriedade_id' => $this->propertyId(),
                'ano_inicio' => $anoInicio,
            ],
            [
                'descricao' => $this->anoAgricolaLabel($anoInicio),
                'data_inicio' => sprintf('%04d-07-01', $anoInicio),
                'data_fim' => sprintf('%04d-06-30', $anoInicio + 1),
                'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: null,
            ]
        );

        return $anoInicio;
    }

    public function criarAtividadePlanejada(array $dados, ?int $usuarioId): int
    {
        $propertyId = $this->propertyId();
        $safraId = $this->idDaPropriedade('safras', $dados['safra_id'] ?? null);
        abort_if(!$safraId, 422, 'Selecione uma safra valida para planejar a atividade.');

        $tipo = in_array($dados['tipo'] ?? '', array_keys($this->tiposAtividade()), true)
            ? $dados['tipo']
            : 'outro';

        DB::table('atividades_campo')->insert([
            'propriedade_id' => $propertyId,
            'safra_id' => $safraId,
            'talhao_id' => $this->idDaPropriedade('talhoes', $dados['talhao_id'] ?? null),
            'area_executada' => $this->nullableDecimal($dados['area_executada'] ?? null),
            'tipo' => $tipo,
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => ($dados['data_fim'] ?? null) ?: null,
            'status' => 'planejada',
            'descricao' => trim((string)($dados['descricao'] ?? '')) ?: $this->tiposAtividade()[$tipo],
            'responsavel' => trim((string)($dados['responsavel'] ?? '')) ?: null,
            'servico' => trim((string)($dados['servico'] ?? '')) ?: null,
            'produto' => trim((string)($dados['produto'] ?? '')) ?: null,
            'dose' => null,
            'custo_estimado' => $this->money($dados['custo_estimado'] ?? 0),
            'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: 'Planejamento operacional da safra',
            'usuario_id' => $usuarioId,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function excluirAtividadePlanejada(int $id): void
    {
        DB::table('atividades_campo')
            ->where('id', $id)
            ->where('propriedade_id', $this->propertyId())
            ->where('status', 'planejada')
            ->delete();
    }

    public function adicionarDespesaPlanejada(array $dados, ?int $usuarioId): int
    {
        $propertyId = $this->propertyId();
        $safra = DB::table('safras')
            ->where('id', $this->idDaPropriedade('safras', $dados['safra_id'] ?? null))
            ->where('propriedade_id', $propertyId)
            ->first();
        abort_if(!$safra, 422, 'Selecione uma safra valida para adicionar a despesa planejada.');

        $categoriaId = (int)($dados['categoria_id'] ?? 0);
        abort_if($categoriaId <= 0 || !DB::table('categorias')->where('id', $categoriaId)->where('ativo', 1)->exists(), 422, 'Informe uma categoria valida.');

        $valor = $this->money($dados['valor_projetado'] ?? 0);
        abort_if($valor <= 0, 422, 'Informe um valor maior que zero.');

        DB::table('financeiro_projecoes')->insert([
            'propriedade_id' => $propertyId,
            'safra_id' => (int)$safra->id,
            'cultura_id' => ($dados['cultura_id'] ?? null) ?: null,
            'tipo_lancamento' => 'despesa',
            'tipo_safra' => str_contains(strtolower((string)$safra->descricao), 'safrinha') ? 'safrinha' : 'principal',
            'ano_safra' => $safra->descricao,
            'mes_referencia' => $dados['mes_referencia'].'-01',
            'categoria_id' => $categoriaId,
            'subcategoria_id' => ($dados['subcategoria_id'] ?? null) ?: null,
            'quantidade' => 0,
            'unidade' => null,
            'valor_unitario' => 0,
            'valor_projetado' => $valor,
            'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: null,
            'usuario_id' => $usuarioId,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function adicionarInsumoPlanejado(array $dados, ?int $usuarioId): int
    {
        $propertyId = $this->propertyId();
        $safra = DB::table('safras')
            ->where('id', $this->idDaPropriedade('safras', $dados['safra_id'] ?? null))
            ->where('propriedade_id', $propertyId)
            ->first();
        abort_if(!$safra, 422, 'Selecione uma safra valida para adicionar o insumo planejado.');

        $categoriaId = (int)($dados['categoria_id'] ?? 0);
        abort_if($categoriaId <= 0 || !DB::table('categorias')->where('id', $categoriaId)->where('ativo', 1)->exists(), 422, 'Informe uma categoria valida.');

        $quantidade = $this->decimal($dados['quantidade'] ?? 0);
        $valorUnitario = $this->money($dados['valor_unitario'] ?? 0);
        $valorTotal = $quantidade > 0 && $valorUnitario > 0 ? round($quantidade * $valorUnitario, 2) : 0;
        abort_if($valorTotal <= 0, 422, 'Informe quantidade e valor unitario maiores que zero.');

        $culturaId = ($dados['cultura_id'] ?? null) ?: ($safra->cultura_id ?? null);
        if ($culturaId && !DB::table('culturas')->where('id', (int)$culturaId)->exists()) {
            $culturaId = null;
        }

        DB::table('financeiro_projecoes')->insert([
            'propriedade_id' => $propertyId,
            'safra_id' => (int)$safra->id,
            'cultura_id' => $culturaId ? (int)$culturaId : null,
            'tipo_lancamento' => 'despesa',
            'tipo_safra' => str_contains(strtolower((string)$safra->descricao), 'safrinha') ? 'safrinha' : 'principal',
            'ano_safra' => $safra->descricao,
            'mes_referencia' => $dados['data_utilizacao'],
            'categoria_id' => $categoriaId,
            'subcategoria_id' => ($dados['subcategoria_id'] ?? null) ?: null,
            'quantidade' => $quantidade,
            'unidade' => trim((string)($dados['unidade'] ?? '')) ?: null,
            'valor_unitario' => $valorUnitario,
            'valor_projetado' => $valorTotal,
            'observacoes' => trim((string)($dados['observacoes'] ?? '')) ?: 'Insumo planejado da safra',
            'usuario_id' => $usuarioId,
        ]);

        return (int)DB::getPdo()->lastInsertId();
    }

    public function copiarSafraAnterior(array $dados, ?int $usuarioId): int
    {
        $propertyId = $this->propertyId();
        $safraDestinoId = $this->idDaPropriedade('safras', $dados['safra_id'] ?? null);
        abort_if(!$safraDestinoId, 422, 'Selecione uma safra valida para receber o planejamento.');

        $safraDestino = DB::table('safras')
            ->where('id', $safraDestinoId)
            ->where('propriedade_id', $propertyId)
            ->first();

        $safraOrigem = DB::table('safras')
            ->where('propriedade_id', $propertyId)
            ->where('id', '!=', $safraDestinoId)
            ->when($safraDestino->data_inicio, fn ($query) => $query->where('data_inicio', '<', $safraDestino->data_inicio))
            ->orderByDesc('data_inicio')
            ->orderByDesc('id')
            ->first();

        abort_if(!$safraOrigem, 422, 'Nao encontrei uma safra anterior para copiar.');

        $culturaId = ($dados['cultura_id'] ?? null) ?: null;
        $origemRows = DB::table('financeiro_projecoes')
            ->where('propriedade_id', $propertyId)
            ->where('safra_id', $safraOrigem->id)
            ->where(function ($query) use ($culturaId) {
                $query->where('cultura_id', $culturaId)->orWhereNull('cultura_id');
            })
            ->where('tipo_lancamento', 'despesa')
            ->orderBy('mes_referencia')
            ->orderBy('categoria_id')
            ->orderBy('id')
            ->get();

        abort_if($origemRows->isEmpty(), 422, 'A safra anterior nao possui planejamento cadastrado.');

        DB::transaction(function () use ($propertyId, $safraDestino, $safraOrigem, $origemRows, $culturaId, $usuarioId) {
            DB::table('financeiro_projecoes')
                ->where('propriedade_id', $propertyId)
                ->where('safra_id', $safraDestino->id)
                ->where(function ($query) use ($culturaId) {
                    $query->where('cultura_id', $culturaId)->orWhereNull('cultura_id');
                })
                ->where('tipo_lancamento', 'despesa')
                ->delete();

            foreach ($origemRows as $row) {
                $observacoes = trim((string)($row->observacoes ?? ''));
                $observacoes = $observacoes !== ''
                    ? $observacoes.' | Copiado de '.$safraOrigem->descricao
                    : 'Copiado de '.$safraOrigem->descricao;

                DB::table('financeiro_projecoes')->insert([
                    'propriedade_id' => $propertyId,
                    'safra_id' => $safraDestino->id,
                    'cultura_id' => $culturaId,
                    'tipo_lancamento' => 'despesa',
                    'tipo_safra' => $row->tipo_safra ?: 'principal',
                    'ano_safra' => $safraDestino->descricao,
                    'mes_referencia' => $this->shiftMonth($safraOrigem->data_inicio, $row->mes_referencia, $safraDestino->data_inicio),
                    'categoria_id' => (int)$row->categoria_id,
                    'subcategoria_id' => $row->subcategoria_id ?: null,
                    'quantidade' => $row->quantidade,
                    'unidade' => $row->unidade,
                    'valor_unitario' => $row->valor_unitario,
                    'valor_projetado' => $row->valor_projetado,
                    'observacoes' => $observacoes,
                    'recorrencia_grupo' => null,
                    'usuario_id' => $usuarioId,
                ]);
            }
        });

        return $origemRows->count();
    }

    private function projecoes(int $propertyId, ?int $safraId = null, int $limit = 120)
    {
        return DB::table('financeiro_projecoes as fp')
            ->leftJoin('safras', 'safras.id', '=', 'fp.safra_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'fp.categoria_id')
            ->where('fp.propriedade_id', $propertyId)
            ->when($safraId, fn ($query) => $query->where('fp.safra_id', $safraId))
            ->orderByDesc('fp.mes_referencia')
            ->orderByDesc('fp.id')
            ->limit($limit)
            ->get([
                'fp.id',
                'fp.tipo_lancamento as tipo_lancamento_raw',
                'fp.mes_referencia',
                'fp.quantidade',
                'fp.unidade',
                'fp.valor_unitario',
                'fp.valor_projetado as valor_projetado_raw',
                'fp.categoria_id',
                'fp.observacoes',
                'safras.descricao as safra',
                'categorias.nome as categoria',
            ])
            ->map(function ($row) {
                $row->tipo_lancamento = $row->tipo_lancamento_raw === 'receita' ? 'Receita' : 'Despesa';
                $row->valor_unitario_formatado = FarmFormat::money($row->valor_unitario);
                $row->valor_projetado_formatado = FarmFormat::money($row->valor_projetado_raw);
                return $row;
            });
    }

    private function safraFiltro($safras, ?string $filtroSafra): ?int
    {
        if (!$filtroSafra || $filtroSafra === 'global') {
            return null;
        }

        $id = (int)$filtroSafra;

        return $id > 0 && $safras->contains('id', $id) ? $id : null;
    }

    private function totalProjetado(int $propertyId, ?int $safraId): float
    {
        return (float)DB::table('financeiro_projecoes')
            ->where('propriedade_id', $propertyId)
            ->when($safraId, fn ($query) => $query->where('safra_id', $safraId))
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_lancamento = 'receita' THEN valor_projetado ELSE -valor_projetado END), 0) as total")
            ->value('total');
    }

    private function totalRealizado(int $propertyId, ?int $safraId): float
    {
        $receitas = (float)DB::table('receitas')
            ->where('propriedade_id', $propertyId)
            ->where('status', '!=', 'cancelado')
            ->where('status_aprovacao', '!=', 'reprovada')
            ->when($safraId, fn ($query) => $query->where('safra_id', $safraId))
            ->sum('valor_total');

        $despesas = (float)DB::table('despesas')
            ->where('propriedade_id', $propertyId)
            ->where('status_pagamento', '!=', 'cancelado')
            ->where('status_aprovacao', '!=', 'reprovada')
            ->when($safraId, fn ($query) => $query->where('safra_id', $safraId))
            ->sum('valor_total');

        return $receitas - $despesas;
    }

    private function planejamentoMensal(int $propertyId, ?int $safraId)
    {
        $projetado = DB::table('financeiro_projecoes')
            ->where('propriedade_id', $propertyId)
            ->when($safraId, fn ($query) => $query->where('safra_id', $safraId))
            ->selectRaw("DATE_FORMAT(mes_referencia, '%Y-%m') as mes, SUM(CASE WHEN tipo_lancamento = 'receita' THEN valor_projetado ELSE -valor_projetado END) as projetado, 0 as realizado")
            ->groupByRaw("DATE_FORMAT(mes_referencia, '%Y-%m')");

        $despesas = DB::table('despesas')
                    ->where('propriedade_id', $propertyId)
                    ->where('status_pagamento', '!=', 'cancelado')
                    ->where('status_aprovacao', '!=', 'reprovada')
                    ->when($safraId, fn ($query) => $query->where('safra_id', $safraId))
                    ->selectRaw("DATE_FORMAT(data_lancamento, '%Y-%m') as mes, 0 as projetado, -SUM(valor_total) as realizado")
                    ->groupByRaw("DATE_FORMAT(data_lancamento, '%Y-%m')");

        return DB::query()
            ->fromSub(
                DB::table('receitas')
                    ->where('propriedade_id', $propertyId)
                    ->where('status', '!=', 'cancelado')
                    ->where('status_aprovacao', '!=', 'reprovada')
                    ->when($safraId, fn ($query) => $query->where('safra_id', $safraId))
                    ->selectRaw("DATE_FORMAT(data_venda, '%Y-%m') as mes, 0 as projetado, SUM(valor_total) as realizado")
                    ->groupByRaw("DATE_FORMAT(data_venda, '%Y-%m')")
                    ->unionAll($despesas)
                    ->unionAll($projetado),
                'planejamento'
            )
            ->selectRaw('mes, SUM(projetado) as projetado, SUM(realizado) as realizado')
            ->groupBy('mes')
            ->orderByDesc('mes')
            ->limit(36)
            ->get()
            ->map(function ($row) {
                $row->projetado_formatado = FarmFormat::money($row->projetado);
                $row->realizado_formatado = FarmFormat::money($row->realizado);
                $row->percentual = (float)$row->projetado > 0 ? ((float)$row->realizado / (float)$row->projetado) * 100 : 0;
                $row->percentual_formatado = number_format($row->percentual, 2, ',', '.').'%';
                return $row;
            });
    }

    private function planejamentoCategorias(int $propertyId, ?int $safraId)
    {
        return DB::table('categorias as c')
            ->leftJoin('financeiro_projecoes as fp', function ($join) use ($propertyId, $safraId) {
                $join->on('fp.categoria_id', '=', 'c.id')
                    ->where('fp.propriedade_id', '=', $propertyId);

                if ($safraId) {
                    $join->where('fp.safra_id', '=', $safraId);
                }
            })
            ->where('c.ativo', 1)
            ->groupBy('c.id', 'c.nome', 'c.cor')
            ->get([
                'c.id',
                'c.nome',
                'c.cor',
                DB::raw("COALESCE(SUM(CASE WHEN fp.tipo_lancamento = 'receita' THEN fp.valor_projetado ELSE -fp.valor_projetado END), 0) as projetado"),
                DB::raw("COALESCE((
                    SELECT SUM(r.valor_total)
                    FROM receitas r
                    WHERE r.propriedade_id = {$propertyId}
                      AND r.categoria_id = c.id
                      AND r.status != 'cancelado'
                      AND r.status_aprovacao != 'reprovada'
                      ".($safraId ? "AND r.safra_id = {$safraId}" : '')."
                ), 0) - COALESCE((
                    SELECT SUM(d.valor_total)
                    FROM despesas d
                    WHERE d.propriedade_id = {$propertyId}
                      AND d.categoria_id = c.id
                      AND d.status_pagamento != 'cancelado'
                      AND d.status_aprovacao != 'reprovada'
                      ".($safraId ? "AND d.safra_id = {$safraId}" : '')."
                ), 0) as realizado"),
            ])
            ->filter(fn ($row) => (float)$row->projetado > 0 || (float)$row->realizado > 0)
            ->sortByDesc(fn ($row) => (float)$row->projetado + (float)$row->realizado)
            ->take(20)
            ->values()
            ->map(function ($row) {
                $row->projetado_formatado = FarmFormat::money($row->projetado);
                $row->realizado_formatado = FarmFormat::money($row->realizado);
                $row->percentual = (float)$row->projetado > 0 ? ((float)$row->realizado / (float)$row->projetado) * 100 : 0;
                $row->percentual_formatado = number_format($row->percentual, 2, ',', '.').'%';
                return $row;
            });
    }

    private function find(int $id)
    {
        return DB::table('financeiro_projecoes')
            ->where('id', $id)
            ->where('propriedade_id', $this->propertyId())
            ->firstOrFail();
    }

    private function payload(array $dados, ?int $usuarioId): array
    {
        return [
            'propriedade_id' => $this->propertyId(),
            'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null),
            'cultura_id' => ($dados['cultura_id'] ?? null) ?: null,
            'tipo_lancamento' => $dados['tipo_lancamento'],
            'tipo_safra' => $dados['tipo_safra'] ?: 'principal',
            'ano_safra' => trim($dados['ano_safra']),
            'mes_referencia' => $dados['mes_referencia'],
            'categoria_id' => (int)$dados['categoria_id'],
            'subcategoria_id' => ($dados['subcategoria_id'] ?? null) ?: null,
            'quantidade' => $this->decimal($dados['quantidade'] ?? 0),
            'unidade' => trim($dados['unidade'] ?? '') ?: null,
            'valor_unitario' => $this->money($dados['valor_unitario'] ?? 0),
            'valor_projetado' => $this->money($dados['valor_projetado'] ?? 0),
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
            'usuario_id' => $usuarioId,
        ];
    }

    private function propertyId(): int
    {
        return app(FarmContext::class)->propertyId();
    }

    private function idDaPropriedade(string $table, mixed $id): ?int
    {
        if (!$id) {
            return null;
        }

        $id = (int)$id;

        return DB::table($table)
            ->where('id', $id)
            ->where('propriedade_id', $this->propertyId())
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

    private function decimal($value): float
    {
        return max(0.0, (float)str_replace(',', '.', trim((string)$value)));
    }

    private function money($value): float
    {
        $value = trim((string)$value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return max(0.0, (float)$value);
    }

    private function nullableDecimal($value): ?float
    {
        $decimal = $this->decimal($value ?? 0);

        return $decimal > 0 ? $decimal : null;
    }

    private function nullableMoney($value): ?float
    {
        $money = $this->money($value ?? 0);

        return $money > 0 ? $money : null;
    }

    private function anoAgricolaInicio(\DateTimeImmutable $dataInicio): int
    {
        $ano = (int)$dataInicio->format('Y');
        $mes = (int)$dataInicio->format('n');

        return $mes >= 7 ? $ano : $ano - 1;
    }

    private function anoAgricolaLabel(int $anoInicio): string
    {
        return $anoInicio.'/'.substr((string)($anoInicio + 1), -2);
    }

    private function tiposAtividade(): array
    {
        return [
            'preparo_solo' => 'Preparo do solo',
            'plantio' => 'Plantio',
            'manejo' => 'Manejo',
            'colheita' => 'Colheita',
            'monitoramento' => 'Monitoramento',
            'recomendacao' => 'Recomendacao',
            'outro' => 'Outra atividade',
        ];
    }

    private function shiftMonth(?string $origemInicio, ?string $origemMes, ?string $destinoInicio): string
    {
        $origemInicioDt = new \DateTimeImmutable(date('Y-m-01', strtotime($origemInicio ?: $origemMes ?: 'now')));
        $origemMesDt = new \DateTimeImmutable(date('Y-m-01', strtotime($origemMes ?: $origemInicio ?: 'now')));
        $destinoDt = new \DateTimeImmutable(date('Y-m-01', strtotime($destinoInicio ?: 'now')));
        $diff = ((int)$origemInicioDt->diff($origemMesDt)->format('%r%y') * 12)
            + (int)$origemInicioDt->diff($origemMesDt)->format('%r%m');

        return $diff !== 0
            ? $destinoDt->modify(($diff > 0 ? '+' : '').$diff.' months')->format('Y-m-01')
            : $destinoDt->format('Y-m-01');
    }
}
