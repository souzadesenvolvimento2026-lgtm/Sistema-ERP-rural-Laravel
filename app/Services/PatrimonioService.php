<?php

namespace App\Services;

use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PatrimonioService
{
    public function pagina(int $propriedadeId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($propriedadeId, $filtros);
        $selectedPatrimonioId = (int) $request->query('patrimonio', 0);
        $selectedPatrimonio = $selectedPatrimonioId > 0
            ? $this->patrimonioComCustos($propriedadeId, $selectedPatrimonioId)
            : null;
        $selectedPatrimonioForm = $selectedPatrimonio
            ? DB::table('maquinas')
                ->where('id', (int) $selectedPatrimonio->id)
                ->where('propriedade_id', $propriedadeId)
                ->first()
            : null;
        $lancamentos = $selectedPatrimonio
            ? $this->lancamentos($propriedadeId, (int) $selectedPatrimonio->id)
            : collect();

        return [
            'activeModule' => 'patrimonio',
            'title' => 'Patrimônios',
            'subtitle' => 'Controle de bens, máquinas, implementos, medidores e custos registrados.',
            'filtros' => $filtros,
            'tipos' => $this->tipos(),
            'tiposLancamento' => $this->tiposLancamento(),
            'rows' => $rows,
            'selectedPatrimonio' => $selectedPatrimonio,
            'selectedPatrimonioForm' => $selectedPatrimonioForm,
            'selectedPatrimonioId' => $selectedPatrimonio?->id,
            'lancamentos' => $lancamentos,
            'safras' => DB::table('safras')->where('propriedade_id', $propriedadeId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')->where('propriedade_id', $propriedadeId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'cards' => [
                ['label' => 'Patrimônios ativos', 'value' => (string) $rows->where('ativo', true)->count(), 'tone' => 'neutral'],
                ['label' => 'Preço total dos patrimônios', 'value' => FarmFormat::money($rows->sum('valor_aquisicao_raw')), 'tone' => 'success'],
                ['label' => 'Custo registrado', 'value' => FarmFormat::money($rows->sum('custo_total_raw')), 'tone' => 'danger'],
                ['label' => 'Combustível', 'value' => FarmFormat::money($rows->sum('combustivel_raw')), 'tone' => 'neutral'],
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId = null, ?UploadedFile $notaFiscal = null): int
    {
        return DB::transaction(function () use ($dados, $propriedadeId, $usuarioId, $notaFiscal): int {
            $arquivoNf = $this->salvarNotaFiscal($notaFiscal);
            DB::table('maquinas')->insert($this->filtrarColunas('maquinas', $this->dadosPatrimonio($dados, $propriedadeId) + [
                'nota_fiscal_arquivo' => $arquivoNf,
                'ativo' => 1,
            ]));

            $patrimonioId = (int) DB::getPdo()->lastInsertId();
            $this->sincronizarFiscal($patrimonioId, $dados, $propriedadeId, $usuarioId, $arquivoNf);
            $this->auditar($usuarioId, 'novo_patrimonio', 'maquinas', $patrimonioId, $propriedadeId, trim($dados['nome']));

            return $patrimonioId;
        });
    }

    public function paraEdicao(int $propriedadeId, int $patrimonioId): object
    {
        $patrimonio = DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_unless($patrimonio, 404);

        return $patrimonio;
    }

    public function atualizar(array $dados, int $propriedadeId, int $patrimonioId, ?int $usuarioId = null, ?UploadedFile $notaFiscal = null): void
    {
        DB::transaction(function () use ($dados, $propriedadeId, $patrimonioId, $usuarioId, $notaFiscal): void {
            $arquivoNf = $this->salvarNotaFiscal($notaFiscal);
            $payload = $this->dadosPatrimonio($dados, $propriedadeId);
            if ($arquivoNf !== null && Schema::hasColumn('maquinas', 'nota_fiscal_arquivo')) {
                $payload['nota_fiscal_arquivo'] = $arquivoNf;
            }

            $alterados = DB::table('maquinas')
                ->where('id', $patrimonioId)
                ->where('propriedade_id', $propriedadeId)
                ->update($payload);

            abort_if($alterados === 0 && ! $this->existe($propriedadeId, $patrimonioId), 404);

            $this->sincronizarFiscal($patrimonioId, $dados, $propriedadeId, $usuarioId, $arquivoNf);
            $this->auditar($usuarioId, 'editar_patrimonio', 'maquinas', $patrimonioId, $propriedadeId, trim($dados['nome']));
        });
    }

    public function alternarStatus(int $propriedadeId, int $patrimonioId, ?int $usuarioId): bool
    {
        $patrimonio = DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->first(['ativo', 'nome']);

        abort_unless($patrimonio, 404);

        $ativo = ! $patrimonio->ativo;

        DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->update(['ativo' => $ativo ? 1 : 0]);

        $this->auditar(
            $usuarioId,
            $ativo ? 'reativar_patrimonio' : 'apagar_patrimonio',
            'maquinas',
            $patrimonioId,
            $propriedadeId,
            $ativo ? 'Patrimônio reativado no cadastro ativo: '.(string) $patrimonio->nome : 'Patrimônio apagado do cadastro ativo: '.(string) $patrimonio->nome
        );

        return $ativo;
    }

    public function atualizarValor(int $propriedadeId, int $patrimonioId, mixed $valorAquisicao, ?int $usuarioId): void
    {
        $patrimonio = DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->first(['id', 'nome']);

        abort_unless($patrimonio, 404);

        $valor = $this->money($valorAquisicao ?? 0);

        DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->update(['valor_aquisicao' => $valor]);

        $this->auditar(
            $usuarioId,
            'atualizar_valor_patrimonio',
            'maquinas',
            $patrimonioId,
            $propriedadeId,
            'Valor do patrimônio atualizado para '.FarmFormat::money($valor)
        );
    }

    public function detalhe(int $propriedadeId, int $patrimonioId): array
    {
        $patrimonio = DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_unless($patrimonio, 404);

        $row = $this->normalizar((object) array_merge((array) $patrimonio, [
            'custo_total' => DB::table('maquina_lancamentos')->where('maquina_id', $patrimonioId)->sum('valor_total'),
            'combustivel' => DB::table('maquina_lancamentos')->where('maquina_id', $patrimonioId)->where('tipo', 'abastecimento')->sum('valor_total'),
            'lancamentos_count' => DB::table('maquina_lancamentos')->where('maquina_id', $patrimonioId)->count(),
        ]));

        $lancamentos = $this->lancamentos($propriedadeId, $patrimonioId);

        return [
            'activeModule' => 'patrimonio',
            'title' => $row->nome,
            'subtitle' => 'Histórico de custos, abastecimentos e manutenções do patrimônio.',
            'patrimonio' => $row,
            'lancamentos' => $lancamentos,
            'safras' => DB::table('safras')->where('propriedade_id', $propriedadeId)->orderByDesc('data_inicio')->get(['id', 'descricao']),
            'talhoes' => DB::table('talhoes')->where('propriedade_id', $propriedadeId)->where('ativo', 1)->orderBy('nome')->get(['id', 'nome']),
            'tiposLancamento' => $this->tiposLancamento(),
            'cards' => [
                ['label' => 'Valor de aquisição', 'value' => $row->valor_aquisicao, 'tone' => 'success'],
                ['label' => 'Custo registrado', 'value' => $row->custo_total, 'tone' => 'danger'],
                ['label' => 'Combustível', 'value' => $row->combustivel, 'tone' => 'warning'],
                ['label' => 'Lançamentos', 'value' => (string) $lancamentos->count(), 'tone' => 'success'],
            ],
        ];
    }

    public function criarLancamento(array $dados, int $propriedadeId, int $patrimonioId, ?int $usuarioId, ?UploadedFile $comprovante = null): void
    {
        abort_unless(
            DB::table('maquinas')->where('id', $patrimonioId)->where('propriedade_id', $propriedadeId)->exists(),
            404
        );

        $quantidade = $this->money($dados['quantidade'] ?? 0);
        $valorUnitario = $this->money($dados['valor_unitario'] ?? 0);
        $valorTotal = $this->money($dados['valor_total'] ?? 0);
        if ($valorTotal <= 0 && $quantidade > 0 && $valorUnitario > 0) {
            $valorTotal = $quantidade * $valorUnitario;
        }

        $horimetro = $this->nullableMoney($dados['horimetro'] ?? null);
        $odometro = $this->nullableMoney($dados['odometro'] ?? null);
        $comprovanteNome = $this->salvarComprovante($comprovante);

        DB::transaction(function () use ($dados, $propriedadeId, $patrimonioId, $usuarioId, $quantidade, $valorUnitario, $valorTotal, $horimetro, $odometro, $comprovanteNome) {
            DB::table('maquina_lancamentos')->insert([
                'propriedade_id' => $propriedadeId,
                'maquina_id' => $patrimonioId,
                'safra_id' => $this->idDaPropriedade('safras', $dados['safra_id'] ?? null, $propriedadeId),
                'talhao_id' => $this->idDaPropriedade('talhoes', $dados['talhao_id'] ?? null, $propriedadeId),
                'tipo' => $dados['tipo'],
                'data_lancamento' => $dados['data_lancamento'],
                'descricao' => trim($dados['descricao']),
                'fornecedor' => trim($dados['fornecedor'] ?? '') ?: null,
                'quantidade' => $quantidade ?: null,
                'unidade' => trim($dados['unidade'] ?? '') ?: null,
                'valor_unitario' => $valorUnitario,
                'valor_total' => $valorTotal,
                'horimetro' => $horimetro,
                'odometro' => $odometro,
                'proxima_revisao_horas' => $this->nullableMoney($dados['proxima_revisao_horas'] ?? null),
                'comprovante' => $comprovanteNome,
                'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
                'usuario_id' => $usuarioId,
            ]);

            DB::table('maquinas')
                ->where('id', $patrimonioId)
                ->where('propriedade_id', $propriedadeId)
                ->update([
                    'horimetro_atual' => DB::raw('GREATEST(COALESCE(horimetro_atual, 0), '.($horimetro ?? 'COALESCE(horimetro_atual, 0)').')'),
                    'odometro_atual' => DB::raw('GREATEST(COALESCE(odometro_atual, 0), '.($odometro ?? 'COALESCE(odometro_atual, 0)').')'),
                ]);
        });
    }

    public function tipos(): array
    {
        return [
            'trator' => 'Trator',
            'colheitadeira' => 'Colheitadeira',
            'plantadeira' => 'Plantadeira',
            'pulverizador' => 'Pulverizador',
            'caminhao' => 'Caminhao',
            'implemento' => 'Implemento',
            'outro' => 'Outro',
        ];
    }

    private function salvarComprovante(?UploadedFile $arquivo): ?string
    {
        if (! $arquivo) {
            return null;
        }

        File::ensureDirectoryExists(base_path('../uploads/comprovantes'));

        $nome = 'maq_'.uniqid().'.'.$arquivo->getClientOriginalExtension();
        $arquivo->move(base_path('../uploads/comprovantes'), $nome);

        return $nome;
    }

    private function salvarNotaFiscal(?UploadedFile $arquivo): ?string
    {
        if (! $arquivo) {
            return null;
        }

        File::ensureDirectoryExists(base_path('../uploads/comprovantes'));

        $nome = 'patnf_'.uniqid().'.'.$arquivo->getClientOriginalExtension();
        $arquivo->move(base_path('../uploads/comprovantes'), $nome);

        return $nome;
    }

    private function dadosPatrimonio(array $dados, int $propriedadeId): array
    {
        return $this->filtrarColunas('maquinas', [
            'propriedade_id' => $propriedadeId,
            'nome' => trim($dados['nome']),
            'tipo' => $dados['tipo'] ?: 'outro',
            'tipo_outro' => trim($dados['tipo_outro'] ?? '') ?: null,
            'marca_modelo' => trim($dados['marca_modelo'] ?? '') ?: null,
            'identificacao' => trim($dados['identificacao'] ?? '') ?: null,
            'descricao_patrimonio' => trim($dados['descricao_patrimonio'] ?? '') ?: null,
            'ano' => ($dados['ano'] ?? null) ?: null,
            'valor_aquisicao' => $this->money($dados['valor_aquisicao'] ?? 0),
            'data_aquisicao' => ($dados['data_aquisicao'] ?? null) ?: null,
            'fornecedor' => trim($dados['fornecedor'] ?? '') ?: null,
            'fornecedor_doc' => preg_replace('/\D+/', '', (string) ($dados['fornecedor_doc'] ?? '')) ?: null,
            'nota_fiscal_numero' => trim($dados['nota_fiscal_numero'] ?? '') ?: null,
            'nota_fiscal_serie' => trim($dados['nota_fiscal_serie'] ?? '') ?: null,
            'nota_fiscal_chave' => preg_replace('/\D+/', '', (string) ($dados['nota_fiscal_chave'] ?? '')) ?: null,
            'controla_horimetro' => (bool) ($dados['controla_horimetro'] ?? false),
            'controla_odometro' => (bool) ($dados['controla_odometro'] ?? false),
            'horimetro_atual' => ! empty($dados['controla_horimetro']) ? $this->nullableMoney($dados['horimetro_atual'] ?? null) : null,
            'odometro_atual' => ! empty($dados['controla_odometro']) ? $this->nullableMoney($dados['odometro_atual'] ?? null) : null,
        ]);
    }

    private function existe(int $propriedadeId, int $patrimonioId): bool
    {
        return DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->exists();
    }

    private function sincronizarFiscal(int $patrimonioId, array $dados, int $propriedadeId, ?int $usuarioId, ?string $arquivoNf = null): void
    {
        $numero = trim((string) ($dados['nota_fiscal_numero'] ?? ''));
        if ($numero === '' && $arquivoNf === null) {
            return;
        }

        $patrimonio = DB::table('maquinas')
            ->where('id', $patrimonioId)
            ->where('propriedade_id', $propriedadeId)
            ->first(['nf_entrada_id', 'documento_id', 'nota_fiscal_arquivo']);

        abort_unless($patrimonio, 404);

        $nfEntradaId = $this->sincronizarEntradaFiscal($patrimonioId, $dados, $propriedadeId, $usuarioId, $patrimonio->nf_entrada_id ? (int) $patrimonio->nf_entrada_id : null);
        $documentoId = $this->sincronizarDocumentoFiscal($patrimonioId, $dados, $propriedadeId, $usuarioId, $arquivoNf ?: (string) ($patrimonio->nota_fiscal_arquivo ?? ''), $patrimonio->documento_id ? (int) $patrimonio->documento_id : null);

        $vinculos = $this->filtrarColunas('maquinas', array_filter([
            'nf_entrada_id' => $nfEntradaId,
            'documento_id' => $documentoId,
        ], fn ($value) => $value !== null));

        if ($vinculos !== []) {
            DB::table('maquinas')
                ->where('id', $patrimonioId)
                ->where('propriedade_id', $propriedadeId)
                ->update($vinculos);
        }
    }

    private function sincronizarEntradaFiscal(int $patrimonioId, array $dados, int $propriedadeId, ?int $usuarioId, ?int $nfEntradaId = null): ?int
    {
        $numero = trim((string) ($dados['nota_fiscal_numero'] ?? ''));
        if ($numero === '') {
            return $nfEntradaId;
        }

        $data = ($dados['data_aquisicao'] ?? null) ?: date('Y-m-d');
        $valor = $this->money($dados['valor_aquisicao'] ?? 0);
        $payload = $this->filtrarColunas('nf_entradas', [
            'propriedade_id' => $propriedadeId,
            'numero' => $numero,
            'serie' => trim($dados['nota_fiscal_serie'] ?? '') ?: null,
            'chave_acesso' => preg_replace('/\D+/', '', (string) ($dados['nota_fiscal_chave'] ?? '')) ?: null,
            'data_emissao' => $data,
            'data_entrada' => $data,
            'fornecedor' => trim($dados['fornecedor'] ?? '') ?: 'Fornecedor nao informado',
            'fornecedor_doc' => preg_replace('/\D+/', '', (string) ($dados['fornecedor_doc'] ?? '')) ?: null,
            'valor_total' => $valor,
            'valor_produtos' => $valor,
            'valor_financeiro_final' => $valor,
            'condicao_pagamento' => 'Aquisição de patrimônio',
            'forma_pagamento' => 'transferencia',
            'observacoes_nota' => 'Entrada criada/atualizada pelo cadastro de patrimônio.',
            'status' => 'rascunho',
            'usuario_id' => $usuarioId,
            'classificar_patrimonio' => 1,
            'patrimonio_id' => $patrimonioId,
            'patrimonio_nome' => trim($dados['nome'] ?? ''),
            'patrimonio_tipo' => $dados['tipo'] ?? 'outro',
            'patrimonio_tipo_outro' => trim($dados['tipo_outro'] ?? '') ?: null,
            'patrimonio_controla_horimetro' => ! empty($dados['controla_horimetro']) ? 1 : 0,
            'patrimonio_controla_odometro' => ! empty($dados['controla_odometro']) ? 1 : 0,
        ]);

        if ($nfEntradaId && DB::table('nf_entradas')->where('id', $nfEntradaId)->where('propriedade_id', $propriedadeId)->exists()) {
            DB::table('nf_entradas')->where('id', $nfEntradaId)->where('propriedade_id', $propriedadeId)->update($payload);

            return $nfEntradaId;
        }

        DB::table('nf_entradas')->insert($payload);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function sincronizarDocumentoFiscal(int $patrimonioId, array $dados, int $propriedadeId, ?int $usuarioId, string $arquivoNf = '', ?int $documentoId = null): ?int
    {
        if ($arquivoNf === '' && ! $documentoId) {
            return null;
        }

        $payload = [
            'propriedade_id' => $propriedadeId,
            'tipo' => 'nota_fiscal',
            'titulo' => 'NF patrimônio - '.trim($dados['nome'] ?? ('Patrimônio #'.$patrimonioId)),
            'numero' => trim($dados['nota_fiscal_numero'] ?? '') ?: null,
            'pessoa' => trim($dados['fornecedor'] ?? '') ?: null,
            'data_documento' => ($dados['data_aquisicao'] ?? null) ?: date('Y-m-d'),
            'valor' => $this->money($dados['valor_aquisicao'] ?? 0),
            'status' => 'conferido',
            'observacoes' => 'Documento vinculado ao patrimônio #'.$patrimonioId.'.',
            'usuario_id' => $usuarioId,
        ];
        if ($arquivoNf !== '') {
            $payload['arquivo'] = $arquivoNf;
        }

        if ($documentoId && DB::table('documentos')->where('id', $documentoId)->where('propriedade_id', $propriedadeId)->exists()) {
            DB::table('documentos')->where('id', $documentoId)->where('propriedade_id', $propriedadeId)->update($payload);

            return $documentoId;
        }

        if ($arquivoNf === '') {
            return null;
        }

        DB::table('documentos')->insert($payload);

        return (int) DB::getPdo()->lastInsertId();
    }

    private function filtrarColunas(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, (string) $column))
            ->all();
    }

    private function filtros(Request $request): array
    {
        $tipo = (string) $request->query('tipo', '');
        if ($tipo !== '' && ! array_key_exists($tipo, $this->tipos())) {
            $tipo = '';
        }

        return [
            'tipo' => $tipo,
            'status' => in_array($request->query('status'), ['ativos', 'inativos', 'todos'], true) ? (string) $request->query('status') : 'ativos',
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function rows(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('maquinas as m')->where('m.propriedade_id', $propriedadeId);

        if ($filtros['status'] === 'ativos') {
            $query->where('m.ativo', 1);
        } elseif ($filtros['status'] === 'inativos') {
            $query->where('m.ativo', 0);
        }

        if ($filtros['tipo'] !== '') {
            $query->where('m.tipo', $filtros['tipo']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('m.nome', 'like', $term)
                    ->orWhere('m.marca_modelo', 'like', $term)
                    ->orWhere('m.identificacao', 'like', $term)
                    ->orWhere('m.fornecedor', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('m.ativo')
            ->orderBy('m.nome')
            ->get([
                'm.id',
                'm.nome',
                'm.tipo',
                'm.tipo_outro',
                'm.marca_modelo',
                'm.identificacao',
                'm.descricao_patrimonio',
                'm.ano',
                'm.valor_aquisicao',
                'm.data_aquisicao',
                'm.fornecedor',
                'm.controla_horimetro',
                'm.controla_odometro',
                'm.horimetro_atual',
                'm.odometro_atual',
                'm.ativo',
                DB::raw('(SELECT COALESCE(SUM(valor_total), 0) FROM maquina_lancamentos ml WHERE ml.maquina_id = m.id) as custo_total'),
                DB::raw("(SELECT COALESCE(SUM(valor_total), 0) FROM maquina_lancamentos ml WHERE ml.maquina_id = m.id AND ml.tipo = 'abastecimento') as combustivel"),
                DB::raw('(SELECT COUNT(*) FROM maquina_lancamentos ml WHERE ml.maquina_id = m.id) as lancamentos_count'),
            ])
            ->map(fn ($row) => $this->normalizar($row));
    }

    private function patrimonioComCustos(int $propriedadeId, int $patrimonioId): ?object
    {
        $row = DB::table('maquinas as m')
            ->where('m.id', $patrimonioId)
            ->where('m.propriedade_id', $propriedadeId)
            ->select([
                'm.*',
                DB::raw('(SELECT COALESCE(SUM(valor_total), 0) FROM maquina_lancamentos ml WHERE ml.maquina_id = m.id) as custo_total'),
                DB::raw("(SELECT COALESCE(SUM(valor_total), 0) FROM maquina_lancamentos ml WHERE ml.maquina_id = m.id AND ml.tipo = 'abastecimento') as combustivel"),
                DB::raw('(SELECT COUNT(*) FROM maquina_lancamentos ml WHERE ml.maquina_id = m.id) as lancamentos_count'),
            ])
            ->first();

        return $row ? $this->normalizar($row) : null;
    }

    private function normalizar($row): object
    {
        return (object) [
            'id' => (int) $row->id,
            'nome' => FarmFormat::value($row->nome),
            'tipo' => $this->tipoLabel((string) $row->tipo, (string) ($row->tipo_outro ?? '')),
            'marca_modelo' => FarmFormat::value($row->marca_modelo),
            'identificacao' => FarmFormat::value($row->identificacao),
            'descricao' => FarmFormat::value($row->descricao_patrimonio),
            'ano' => FarmFormat::value($row->ano),
            'valor_aquisicao_raw' => (float) $row->valor_aquisicao,
            'valor_aquisicao' => FarmFormat::money($row->valor_aquisicao),
            'data_aquisicao' => FarmFormat::date($row->data_aquisicao),
            'fornecedor' => FarmFormat::value($row->fornecedor),
            'fornecedor_doc' => FarmFormat::value($row->fornecedor_doc ?? null),
            'nota_fiscal_numero' => FarmFormat::value($row->nota_fiscal_numero ?? null),
            'nota_fiscal_serie' => FarmFormat::value($row->nota_fiscal_serie ?? null),
            'nota_fiscal_chave' => FarmFormat::value($row->nota_fiscal_chave ?? null),
            'nota_fiscal_arquivo' => trim((string) ($row->nota_fiscal_arquivo ?? '')),
            'nf_entrada_id' => ! empty($row->nf_entrada_id) ? (int) $row->nf_entrada_id : null,
            'documento_id' => ! empty($row->documento_id) ? (int) $row->documento_id : null,
            'horimetro' => $row->controla_horimetro ? FarmFormat::decimal($row->horimetro_atual, 1).' h' : '-',
            'odometro' => $row->controla_odometro ? FarmFormat::decimal($row->odometro_atual, 1).' km' : '-',
            'ativo' => (bool) $row->ativo,
            'status' => (bool) $row->ativo ? 'Ativo' : 'Inativo',
            'custo_total_raw' => (float) $row->custo_total,
            'custo_total' => FarmFormat::money($row->custo_total),
            'combustivel_raw' => (float) $row->combustivel,
            'combustivel' => FarmFormat::money($row->combustivel),
            'lancamentos_count' => (int) $row->lancamentos_count,
        ];
    }

    private function tipoLabel(string $tipo, string $tipoOutro): string
    {
        if ($tipo === 'outro' && trim($tipoOutro) !== '') {
            return $tipoOutro;
        }

        return $this->tipos()[$tipo] ?? ucfirst($tipo ?: 'Outro');
    }

    private function lancamentos(int $propriedadeId, int $patrimonioId): Collection
    {
        return DB::table('maquina_lancamentos as ml')
            ->leftJoin('safras as s', 's.id', '=', 'ml.safra_id')
            ->leftJoin('talhoes as t', 't.id', '=', 'ml.talhao_id')
            ->where('ml.propriedade_id', $propriedadeId)
            ->where('ml.maquina_id', $patrimonioId)
            ->orderByDesc('ml.data_lancamento')
            ->orderByDesc('ml.id')
            ->get([
                'ml.id',
                'ml.tipo',
                'ml.data_lancamento',
                'ml.descricao',
                'ml.fornecedor',
                'ml.quantidade',
                'ml.unidade',
                'ml.valor_unitario',
                'ml.valor_total',
                'ml.horimetro',
                'ml.odometro',
                'ml.comprovante',
                'ml.observacoes',
                's.descricao as safra_nome',
                't.nome as talhao_nome',
            ])
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'tipo' => $this->tipoLancamentoLabel((string) $row->tipo),
                'data' => FarmFormat::date($row->data_lancamento),
                'descricao' => FarmFormat::value($row->descricao),
                'fornecedor' => FarmFormat::value($row->fornecedor),
                'safra' => FarmFormat::value($row->safra_nome),
                'talhao' => FarmFormat::value($row->talhao_nome),
                'quantidade' => $this->quantidade($row->quantidade, $row->unidade),
                'valor_unitario' => FarmFormat::money($row->valor_unitario),
                'valor' => FarmFormat::money($row->valor_total),
                'horimetro' => $row->horimetro ? FarmFormat::decimal($row->horimetro, 1).' h' : '-',
                'odometro' => $row->odometro ? FarmFormat::decimal($row->odometro, 1).' km' : '-',
                'comprovante' => trim((string) ($row->comprovante ?? '')),
                'observacoes' => FarmFormat::value($row->observacoes),
            ]);
    }

    private function tipoLancamentoLabel(string $tipo): string
    {
        return $this->tiposLancamento()[$tipo] ?? FarmFormat::statusLabel($tipo);
    }

    public function tiposLancamento(): array
    {
        return [
            'abastecimento' => 'Abastecimento',
            'manutencao_preventiva' => 'Manutenção preventiva',
            'manutencao_corretiva' => 'Manutenção corretiva',
            'troca_oleo' => 'Troca de óleo',
            'pecas' => 'Peças',
            'seguro' => 'Seguro',
            'outro' => 'Outro',
        ];
    }

    private function quantidade($quantidade, $unidade): string
    {
        if ($quantidade === null || (float) $quantidade <= 0) {
            return '-';
        }

        $texto = FarmFormat::decimal($quantidade, 2);
        $unidade = trim((string) $unidade);

        return $unidade !== '' ? $texto.' '.$unidade : $texto;
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

    private function nullableMoney($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->money($value);
    }

    private function idDaPropriedade(string $table, $id, int $propriedadeId): ?int
    {
        if (! $id) {
            return null;
        }

        $exists = DB::table($table)
            ->where('id', (int) $id)
            ->where('propriedade_id', $propriedadeId)
            ->exists();

        return $exists ? (int) $id : null;
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
