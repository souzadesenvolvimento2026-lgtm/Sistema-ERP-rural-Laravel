<?php

namespace App\Services;

use App\Domain\Production\SafraCapabilities;
use App\Support\FarmFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SafraService
{
    public function __construct(private readonly SafraCapabilities $capabilities) {}

    public function formData(int $propertyId): array
    {
        return [
            'activeModule' => 'safras',
            'culturas' => DB::table('culturas')->orderBy('nome')->get(['id', 'nome']),
            'talhoes' => $this->talhoesComUsos($propertyId),
        ];
    }

    public function pagina(int $propriedadeId, Request $request): array
    {
        $filtros = $this->filtros($request);
        $rows = $this->rows($propriedadeId, $filtros);
        $formData = $this->formData($propriedadeId);

        return [
            ...$formData,
            'title' => 'Safras',
            'subtitle' => 'Acompanhamento das safras, culturas, talhões, produção, planejamento e status.',
            'filtros' => $filtros,
            'rows' => $rows,
            'cards' => [
                ['label' => 'Safras', 'value' => (string) $rows->count(), 'tone' => 'success'],
                ['label' => 'Área plantada', 'value' => FarmFormat::decimal($rows->sum('area_raw'), 2).' ha', 'tone' => 'success'],
                ['label' => 'Produção estimada', 'value' => FarmFormat::decimal($rows->sum('producao_estimada_raw'), 2).' sc/ha', 'tone' => 'warning'],
                ['label' => 'Produção realizada', 'value' => FarmFormat::decimal($rows->sum('producao_realizada_raw'), 2).' sc', 'tone' => 'success'],
            ],
            'statusOptions' => [
                '' => 'Ativas',
                'todas' => 'Todas',
                'planejamento' => 'Planejamento',
                'em_andamento' => 'Em andamento',
                'colhida' => 'Colhida',
                'encerrada' => 'Encerrada',
            ],
        ];
    }

    public function criar(array $dados, int $propriedadeId, ?int $usuarioId): int
    {
        return DB::transaction(function () use ($dados, $propriedadeId, $usuarioId): int {
            $dadosPreparados = $this->prepararDadosParaSalvar($dados, null, $propriedadeId);

            DB::table('safras')->insert([
                'propriedade_id' => $propriedadeId,
                ...$this->payload($dadosPreparados),
            ]);

            $safraId = (int) DB::getPdo()->lastInsertId();
            $this->sincronizarTalhoes($safraId, $propriedadeId, $dadosPreparados['talhoes']);
            $this->auditar($usuarioId, 'salvar_safra', 'safras', $safraId, $propriedadeId, trim($dadosPreparados['descricao']));

            return $safraId;
        });
    }

    public function buscar(int $safraId, int $propriedadeId): object
    {
        $safra = DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->first();

        abort_if($safra === null, 404);

        $safra->talhoes = DB::table('safra_talhoes')
            ->where('safra_id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->pluck('talhao_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $safra;
    }

    public function atualizar(int $safraId, array $dados, int $propriedadeId, ?int $usuarioId): void
    {
        DB::transaction(function () use ($safraId, $dados, $propriedadeId, $usuarioId): void {
            $dadosPreparados = $this->prepararDadosParaSalvar($dados, $safraId, $propriedadeId);

            DB::table('safras')
                ->where('id', $safraId)
                ->where('propriedade_id', $propriedadeId)
                ->update($this->payload($dadosPreparados));

            $this->sincronizarTalhoes($safraId, $propriedadeId, $dadosPreparados['talhoes']);
            $this->auditar($usuarioId, 'salvar_safra', 'safras', $safraId, $propriedadeId, trim($dadosPreparados['descricao']));
        });
    }

    public function atualizarStatus(int $safraId, int $propriedadeId, string $status, ?int $usuarioId): void
    {
        $safra = DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->first(['id', 'descricao', 'status']);

        abort_if(! $safra, 404);

        if (! $this->capabilities->canTransition((string) $safra->status, $status)) {
            throw new RuntimeException('A transição de status solicitada não é permitida para esta safra.');
        }

        if ($status === 'em_andamento') {
            $talhaoIds = DB::table('safra_talhoes')
                ->where('safra_id', $safraId)
                ->where('propriedade_id', $propriedadeId)
                ->pluck('talhao_id')
                ->map(fn ($talhaoId) => (int) $talhaoId)
                ->all();

            $this->garantirTalhoesSemConflitoEmExecucao($safraId, $propriedadeId, $talhaoIds);
        }

        DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->update(['status' => $status]);

        if ($status === 'encerrada') {
            $this->auditar($usuarioId, 'arquivar_safra', 'safras', $safraId, $propriedadeId, (string) ($safra->descricao ?: 'Safra arquivada'));
        } elseif ($status === 'planejamento' && (string) $safra->status === 'encerrada') {
            $this->auditar($usuarioId, 'desarquivar_safra', 'safras', $safraId, $propriedadeId, (string) $safra->descricao);
        }
    }

    public function excluirDefinitivo(int $safraId, int $propriedadeId, ?int $usuarioId, string $senha): void
    {
        $safra = DB::table('safras')
            ->where('id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->first(['id', 'descricao']);

        if (! $safra) {
            throw new RuntimeException('Safra não encontrada nesta propriedade.');
        }

        $hash = $usuarioId ? DB::table('usuarios')->where('id', $usuarioId)->where('ativo', 1)->value('senha') : null;
        if (! $hash || ! password_verify($senha, (string) $hash)) {
            throw new RuntimeException('Senha incorreta. A safra não foi excluída.');
        }

        $dados = $this->dadosLancados($safraId);
        if ($dados !== []) {
            throw new RuntimeException('Não é possível excluir esta safra porque existem dados lançados: '.$this->resumoDados($dados).'. Encerre a safra para preservar o histórico.');
        }

        DB::transaction(function () use ($safraId, $propriedadeId, $usuarioId, $safra): void {
            DB::table('safra_talhoes')
                ->where('safra_id', $safraId)
                ->where('propriedade_id', $propriedadeId)
                ->delete();

            DB::table('safras')
                ->where('id', $safraId)
                ->where('propriedade_id', $propriedadeId)
                ->delete();

            $this->auditar($usuarioId, 'excluir_safra_sem_dados', 'safras', $safraId, $propriedadeId, (string) $safra->descricao);
        });
    }

    private function filtros(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['', 'todas', 'planejamento', 'em_andamento', 'colhida', 'encerrada'], true)) {
            $status = '';
        }

        return [
            'status' => $status,
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    private function rows(int $propriedadeId, array $filtros): Collection
    {
        $query = DB::table('safras as s')
            ->leftJoin('culturas as c', 'c.id', '=', 's.cultura_id')
            ->where('s.propriedade_id', $propriedadeId);

        if ($filtros['status'] === '') {
            $query->whereNotIn('s.status', ['colhida', 'encerrada']);
        } elseif ($filtros['status'] !== 'todas') {
            $query->where('s.status', $filtros['status']);
        }

        if ($filtros['search'] !== '') {
            $term = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('s.descricao', 'like', $term)
                    ->orWhere('c.nome', 'like', $term)
                    ->orWhere('s.observacoes', 'like', $term);
            });
        }

        return $query
            ->orderByDesc('s.data_inicio')
            ->orderByDesc('s.id')
            ->get([
                's.id',
                's.descricao',
                's.safra_referencia',
                's.data_inicio',
                's.data_fim',
                's.area_plantada',
                's.producao_estimada',
                's.producao_realizada',
                's.preco_estimado',
                's.status',
                's.observacoes',
                'c.nome as cultura_nome',
                DB::raw('(SELECT COUNT(*) FROM safra_talhoes st WHERE st.safra_id = s.id AND st.propriedade_id = s.propriedade_id) as talhoes_count'),
                DB::raw('(SELECT COUNT(*) FROM safra_talhoes st WHERE st.safra_id = s.id AND st.propriedade_id = s.propriedade_id AND st.colheita_finalizada_em IS NOT NULL) as talhoes_colhidos'),
                DB::raw('(SELECT COALESCE(SUM(t.area), 0) FROM safra_talhoes st JOIN talhoes t ON t.id = st.talhao_id WHERE st.safra_id = s.id AND st.propriedade_id = s.propriedade_id) as area_talhoes'),
                DB::raw('(SELECT COALESCE(SUM(ct.peso_final_kg), 0) FROM colheita_talhoes ct WHERE ct.safra_id = s.id AND ct.propriedade_id = s.propriedade_id) as peso_colhido'),
            ])
            ->map(function ($row) {
                $normalizado = $this->normalizar($row);
                $normalizado->dados_lancados = $this->dadosLancados((int) $row->id);
                $normalizado->dados_lancados_resumo = $this->resumoDados($normalizado->dados_lancados);

                foreach ($this->capabilities->for(
                    $normalizado->status_key,
                    $normalizado->dados_lancados,
                ) as $capability => $value) {
                    $normalizado->{$capability} = $value;
                }

                if (! $normalizado->can_delete) {
                    $normalizado->delete_block_reason = 'Não pode excluir: '.$normalizado->dados_lancados_resumo;
                }

                return $normalizado;
            });
    }

    private function normalizar($row): object
    {
        $area = (float) ($row->area_plantada ?: $row->area_talhoes);
        $pesoColhido = (float) $row->peso_colhido;
        $sacasRealizadas = $pesoColhido / 60;

        return (object) [
            'id' => (int) $row->id,
            'descricao' => FarmFormat::value($row->descricao),
            'cultura' => FarmFormat::value($row->cultura_nome),
            'referencia' => $this->referenciaLabel((string) $row->safra_referencia),
            'inicio' => FarmFormat::date($row->data_inicio),
            'fim' => FarmFormat::date($row->data_fim),
            'area_raw' => $area,
            'area' => FarmFormat::decimal($area, 2).' ha',
            'producao_estimada_raw' => (float) $row->producao_estimada,
            'producao_estimada' => FarmFormat::decimal($row->producao_estimada, 2).' sc/ha',
            'producao_realizada_raw' => $sacasRealizadas,
            'producao_realizada' => FarmFormat::decimal($sacasRealizadas, 2).' sc',
            'preco_estimado' => FarmFormat::money($row->preco_estimado),
            'status_key' => (string) $row->status,
            'status' => $this->statusLabel((string) $row->status),
            'talhoes_count' => (int) $row->talhoes_count,
            'talhoes_colhidos' => (int) $row->talhoes_colhidos,
            'observacoes' => FarmFormat::value($row->observacoes),
        ];
    }

    private function referenciaLabel(string $referencia): string
    {
        return [
            'primeira' => 'Primeira safra',
            'segunda' => 'Segunda safra',
            'terceira' => 'Terceira safra',
        ][$referencia] ?? ucfirst($referencia ?: '-');
    }

    private function statusLabel(string $status): string
    {
        return FarmFormat::statusLabel($status);
    }

    private function prepararDadosParaSalvar(array $dados, ?int $safraId, int $propriedadeId): array
    {
        $talhaoIds = $this->normalizarTalhaoIds($dados['talhoes'] ?? []);
        $talhoesSelecionados = $this->talhoesValidosDaPropriedade($propriedadeId, $talhaoIds);

        abort_if(
            count($talhaoIds) !== $talhoesSelecionados->count(),
            422,
            'Existe talhão inválido para esta propriedade.'
        );

        if (($dados['status'] ?? 'planejamento') === 'em_andamento') {
            $this->garantirTalhoesSemConflitoEmExecucao($safraId ?? 0, $propriedadeId, $talhaoIds);
        }

        if ($talhoesSelecionados->isNotEmpty()) {
            $dados['area_plantada'] = (string) round((float) $talhoesSelecionados->sum('area'), 2);
        }

        $dados['talhoes'] = $talhaoIds;

        return $dados;
    }

    private function normalizarTalhaoIds(array $talhoes): array
    {
        return collect($talhoes)
            ->map(fn ($talhaoId) => (int) $talhaoId)
            ->filter(fn ($talhaoId) => $talhaoId > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function talhoesValidosDaPropriedade(int $propriedadeId, array $talhaoIds): Collection
    {
        if ($talhaoIds === []) {
            return collect();
        }

        return DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->whereIn('id', $talhaoIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id',
                DB::raw('COALESCE(area, 0) as area'),
            ]);
    }

    private function sincronizarTalhoes(int $safraId, int $propriedadeId, array $talhaoIds): void
    {
        if ($talhaoIds === []) {
            DB::table('safra_talhoes')
                ->where('safra_id', $safraId)
                ->where('propriedade_id', $propriedadeId)
                ->delete();

            return;
        }

        $talhaoIdsVinculados = DB::table('safra_talhoes')
            ->where('safra_id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->pluck('talhao_id')
            ->map(fn ($talhaoId) => (int) $talhaoId)
            ->all();

        DB::table('safra_talhoes')
            ->where('safra_id', $safraId)
            ->where('propriedade_id', $propriedadeId)
            ->whereNotIn('talhao_id', $talhaoIds)
            ->delete();

        $novosTalhaoIds = array_values(array_diff($talhaoIds, $talhaoIdsVinculados));
        if ($novosTalhaoIds === []) {
            return;
        }

        DB::table('safra_talhoes')->insertOrIgnore(
            array_map(
                fn (int $talhaoId): array => [
                    'safra_id' => $safraId,
                    'talhao_id' => $talhaoId,
                    'propriedade_id' => $propriedadeId,
                    'criado_em' => now(),
                ],
                $novosTalhaoIds
            )
        );
    }

    private function garantirTalhoesSemConflitoEmExecucao(int $safraId, int $propriedadeId, array $talhaoIds): void
    {
        $conflitos = $this->talhoesComConflitoEmExecucao($safraId, $propriedadeId, $talhaoIds);
        abort_if(
            $conflitos->isNotEmpty(),
            422,
            'Talhão em execução em outra cultura sem colheita registrada: '.
                $conflitos
                    ->map(fn ($conflito) => $conflito->talhao_nome.' ('.$conflito->safra_nome.')')
                    ->unique()
                    ->implode(', ').
                '. Salve como Planejamento ou registre a colheita primeiro.'
        );
    }

    private function talhoesComUsos(int $propriedadeId): Collection
    {
        $talhoes = DB::table('talhoes')
            ->where('propriedade_id', $propriedadeId)
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get([
                'id',
                'nome',
                DB::raw('COALESCE(area, 0) as area'),
            ]);

        if ($talhoes->isEmpty()) {
            return $talhoes;
        }

        $usosPorTalhao = DB::table('safra_talhoes as st')
            ->join('safras as s', function ($join) {
                $join->on('s.id', '=', 'st.safra_id')
                    ->on('s.propriedade_id', '=', 'st.propriedade_id');
            })
            ->leftJoin('culturas as c', 'c.id', '=', 's.cultura_id')
            ->where('st.propriedade_id', $propriedadeId)
            ->orderByDesc('s.data_inicio')
            ->orderByDesc('s.id')
            ->get([
                'st.talhao_id',
                'st.safra_id',
                's.descricao as safra_nome',
                's.status',
                'c.nome as cultura_nome',
                'st.colheita_finalizada_em',
            ])
            ->groupBy(fn ($uso) => (int) $uso->talhao_id);

        return $talhoes->map(function ($talhao) use ($usosPorTalhao) {
            $talhao->usos = $usosPorTalhao
                ->get((int) $talhao->id, collect())
                ->map(function ($uso): object {
                    $status = (string) $uso->status;

                    return (object) [
                        'safra_id' => (int) $uso->safra_id,
                        'safra_nome' => (string) $uso->safra_nome,
                        'cultura_nome' => (string) ($uso->cultura_nome ?? ''),
                        'status' => $status,
                        'status_label' => $this->statusLabel($status),
                        'colhido' => ! empty($uso->colheita_finalizada_em)
                            || in_array($status, ['colhida', 'encerrada'], true),
                    ];
                })
                ->values();

            return $talhao;
        });
    }

    private function talhoesComConflitoEmExecucao(int $safraId, int $propriedadeId, array $talhaoIds): Collection
    {
        if ($talhaoIds === []) {
            return collect();
        }

        return DB::table('safra_talhoes as st')
            ->join('safras as s', function ($join) {
                $join->on('s.id', '=', 'st.safra_id')
                    ->on('s.propriedade_id', '=', 'st.propriedade_id');
            })
            ->join('talhoes as t', function ($join) {
                $join->on('t.id', '=', 'st.talhao_id')
                    ->on('t.propriedade_id', '=', 'st.propriedade_id');
            })
            ->where('st.propriedade_id', $propriedadeId)
            ->whereIn('st.talhao_id', $talhaoIds)
            ->where('st.safra_id', '!=', $safraId)
            ->where('s.status', 'em_andamento')
            ->whereNull('st.colheita_finalizada_em')
            ->groupBy('st.talhao_id', 't.nome', 's.id', 's.descricao')
            ->orderBy('t.nome')
            ->orderByDesc('s.data_inicio')
            ->get([
                'st.talhao_id',
                't.nome as talhao_nome',
                's.id as safra_id',
                's.descricao as safra_nome',
            ]);
    }

    private function payload(array $dados): array
    {
        return [
            'cultura_id' => ($dados['cultura_id'] ?? null) ?: null,
            'safra_referencia' => $dados['safra_referencia'] ?: 'primeira',
            'descricao' => trim($dados['descricao']),
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => ($dados['data_fim'] ?? null) ?: null,
            'area_plantada' => $this->decimal($dados['area_plantada'] ?? 0),
            'producao_estimada' => $this->decimal($dados['producao_estimada'] ?? 0),
            'preco_estimado' => $this->money($dados['preco_estimado'] ?? 0),
            'status' => $dados['status'] ?: 'planejamento',
            'observacoes' => trim($dados['observacoes'] ?? '') ?: null,
        ];
    }

    private function decimal($value): float
    {
        $value = str_replace(',', '.', trim((string) $value));

        return max(0.0, (float) $value);
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

    private function dadosLancados(int $safraId): array
    {
        $labels = [
            'alertas' => 'alertas',
            'atividades_campo' => 'atividades',
            'colheita_talhoes' => 'colheita',
            'contratos' => 'contratos',
            'despesas' => 'despesas',
            'documentos' => 'documentos',
            'financeiro_projecoes' => 'planejamento financeiro',
            'mapas_colheita' => 'mapas de colheita',
            'maquina_lancamentos' => 'patrimônio/máquinas',
            'nf_entradas' => 'entrada de NF',
            'nf_entrada_itens' => 'itens de NF',
            'notas_fiscais' => 'notas fiscais',
            'orcamentos' => 'orcamentos',
            'receitas' => 'receitas',
        ];

        $detalhes = [];
        foreach ($labels as $table => $label) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'safra_id')) {
                continue;
            }

            $qtd = DB::table($table)->where('safra_id', $safraId)->count();
            if ($qtd > 0) {
                $detalhes[$label] = $qtd;
            }
        }

        return $detalhes;
    }

    private function resumoDados(array $detalhes): string
    {
        return collect($detalhes)
            ->map(fn ($qtd, $label) => $label.' ('.(int) $qtd.')')
            ->implode(', ');
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
